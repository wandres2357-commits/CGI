<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config\Env;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class AuthService
{
    private const DUMMY_PASSWORD_HASH =
        '$2y$12$abcdefghijklmnopqrstuu'
        . '7QyVjfnqY9rZkBvUwPsPV7bRwg9yK';

    public function __construct(
        private readonly PDO $pdo,
        private readonly UserRepository $users,
        private readonly SessionRepository $sessions,
        private readonly AuditService $audit
    ) {
    }

    public function attempt(
        string $identifier,
        string $password,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): AuthResult {
        $identifier = strtolower(
            trim($identifier)
        );

        if ($identifier === '' || $password === '') {
            $this->recordFailure(
                userId: null,
                reason: 'CREDENCIALES_INCOMPLETAS',
                identifier: $identifier !== ''
                    ? $identifier
                    : null,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->clearSensitiveString($password);

            return AuthResult::failure();
        }

        $user = $this->users
            ->findByIdentifier($identifier);

        if ($user === null) {
            password_verify(
                $password,
                self::DUMMY_PASSWORD_HASH
            );

            $this->recordFailure(
                userId: null,
                reason: 'CREDENCIALES_INVALIDAS',
                identifier: $identifier,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->clearSensitiveString($password);

            return AuthResult::failure();
        }

        if (!$user['activo']) {
            password_verify(
                $password,
                (string) $user['password_hash']
            );

            $this->recordFailure(
                userId: (int) $user['id'],
                reason: 'CUENTA_INACTIVA',
                identifier: $identifier,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->clearSensitiveString($password);

            return AuthResult::failure();
        }

        $user = $this->clearExpiredBlock($user);

        if ($this->isBlocked($user)) {
            password_verify(
                $password,
                (string) $user['password_hash']
            );

            $this->recordFailure(
                userId: (int) $user['id'],
                reason: 'CUENTA_BLOQUEADA',
                identifier: $identifier,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->clearSensitiveString($password);

            return AuthResult::failure(
                'ACCOUNT_BLOCKED'
            );
        }

        if (
            !password_verify(
                $password,
                (string) $user['password_hash']
            )
        ) {
            $this->registerFailedAttempt(
                user: $user,
                identifier: $identifier,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->clearSensitiveString($password);

            return AuthResult::failure();
        }

        try {
            return $this->completeSuccessfulLogin(
                user: $user,
                password: $password,
                identifier: $identifier,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );
        } finally {
            $this->clearSensitiveString($password);
        }
    }

    public function logout(
        string $reason = 'CIERRE_USUARIO',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        SessionManager::start();

        $user = SessionManager::user();
        $sessionId = SessionManager::id();

        if ($user === null) {
            SessionManager::destroy();

            return;
        }

        $this->pdo->beginTransaction();

        try {
            $this->sessions->close(
                $sessionId,
                $reason
            );

            $this->audit->recordAuthentication(
                userId: (int) $user['id'],
                action: 'CIERRE_SESION',
                result: 'EXITOSO',
                reason: $reason,
                identifier:
                    (string) $user['nombre_usuario'],
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            SessionManager::destroy();

            throw $exception;
        }

        SessionManager::destroy();
    }

    public function sessionIsValid(): bool
    {
        if (!SessionManager::isAuthenticated()) {
            return false;
        }

        $userId = SessionManager::userId();
        $sessionId = SessionManager::id();

        if ($userId === null || $sessionId === '') {
            return false;
        }

        return $this->sessions->isActive(
            $sessionId,
            $userId
        );
    }

    public function touchAuthenticatedSession(): void
    {
        if (!$this->sessionIsValid()) {
            return;
        }

        $this->sessions->touch(
            SessionManager::id()
        );
    }

    private function registerFailedAttempt(
        array $user,
        string $identifier,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $maximumAttempts = max(
            1,
            Env::int(
                'AUTH_MAX_ATTEMPTS',
                5
            )
        );

        $lockMinutes = max(
            1,
            Env::int(
                'AUTH_LOCK_MINUTES',
                15
            )
        );

        $this->pdo->beginTransaction();

        try {
            $attempts = $this->users
                ->incrementFailedAttempts(
                    (int) $user['id']
                );

            $reason = 'CREDENCIALES_INVALIDAS';

            if ($attempts >= $maximumAttempts) {
                $blockedUntil = (
                    new DateTimeImmutable()
                )->modify(
                    sprintf(
                        '+%d minutes',
                        $lockMinutes
                    )
                )->format('Y-m-d H:i:s');

                $this->users->blockUntil(
                    (int) $user['id'],
                    $blockedUntil
                );

                $reason = 'CUENTA_BLOQUEADA';

                $this->audit->recordAuthentication(
                    userId: (int) $user['id'],
                    action: 'BLOQUEO_CUENTA',
                    result: 'EXITOSO',
                    reason:
                        'MAXIMO_INTENTOS_FALLIDOS',
                    identifier: $identifier,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent
                );
            }

            $this->audit->recordAuthentication(
                userId: (int) $user['id'],
                action: 'INICIO_SESION_FALLIDO',
                result: 'FALLIDO',
                reason: $reason,
                identifier: $identifier,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function completeSuccessfulLogin(
        array $user,
        string $password,
        string $identifier,
        ?string $ipAddress,
        ?string $userAgent
    ): AuthResult {
        $maximumHours = max(
            1,
            Env::int(
                'SESSION_MAX_HOURS',
                8
            )
        );

        $maximumSessions = max(
            1,
            Env::int(
                'SESSION_MAX_CONCURRENT',
                2
            )
        );

        SessionManager::authenticate($user);

        $sessionId = SessionManager::id();

        try {
            $this->pdo->beginTransaction();

            if (
                password_needs_rehash(
                    (string) $user['password_hash'],
                    PASSWORD_DEFAULT
                )
            ) {
                $newHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($newHash === false) {
                    throw new RuntimeException(
                        'No fue posible actualizar '
                        . 'el hash de contraseña.'
                    );
                }

                $this->users->updatePasswordHash(
                    userId: (int) $user['id'],
                    passwordHash: $newHash,
                    requireChange:
                        (bool) $user[
                            'debe_cambiar_password'
                        ]
                );

                $this->clearSensitiveString($newHash);
            }

            $this->users->recordSuccessfulLogin(
                (int) $user['id']
            );

            $expiresAt = (
                new DateTimeImmutable()
            )->modify(
                sprintf(
                    '+%d hours',
                    $maximumHours
                )
            )->format('Y-m-d H:i:s');

            $this->sessions->create(
                sessionId: $sessionId,
                userId: (int) $user['id'],
                expiresAt: $expiresAt,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $closedSessions = $this->sessions
                ->closeOldestUntilLimit(
                    userId: (int) $user['id'],
                    maximumSessions:
                        $maximumSessions,
                    currentSessionId:
                        $sessionId
                );

            $this->audit->recordAuthentication(
                userId: (int) $user['id'],
                action: 'INICIO_SESION',
                result: 'EXITOSO',
                reason: 'CREDENCIALES_VALIDAS',
                identifier: $identifier,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            if ($closedSessions !== []) {
                $this->audit->record(
                    userId: (int) $user['id'],
                    module: 'AUTENTICACION',
                    action:
                        'CIERRE_SESIONES_ANTIGUAS',
                    result: 'EXITOSO',
                    entityType: 'USUARIO',
                    entityId: (int) $user['id'],
                    newValues: [
                        'cantidad' =>
                            count($closedSessions),
                    ],
                    reason:
                        'LIMITE_SESIONES_SIMULTANEAS',
                    ipAddress: $ipAddress,
                    userAgent: $userAgent
                );
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            SessionManager::destroy();

            throw $exception;
        }

        $freshUser = $this->users->findById(
            (int) $user['id']
        );

        if ($freshUser === null) {
            $this->sessions->close(
                $sessionId,
                'ERROR_POST_AUTENTICACION'
            );

            SessionManager::destroy();

            throw new RuntimeException(
                'No fue posible recuperar '
                . 'el usuario autenticado.'
            );
        }

        return AuthResult::success($freshUser);
    }

    private function clearExpiredBlock(
        array $user
    ): array {
        if ($user['bloqueado_hasta'] === null) {
            return $user;
        }

        $blockedUntil = new DateTimeImmutable(
            (string) $user['bloqueado_hasta']
        );

        if (
            $blockedUntil
            > new DateTimeImmutable()
        ) {
            return $user;
        }

        $this->users->clearExpiredBlock(
            (int) $user['id']
        );

        return $this->users->findById(
            (int) $user['id']
        ) ?? $user;
    }

    private function isBlocked(
        array $user
    ): bool {
        if ($user['bloqueado_hasta'] === null) {
            return false;
        }

        return new DateTimeImmutable(
            (string) $user['bloqueado_hasta']
        ) > new DateTimeImmutable();
    }

    private function recordFailure(
        ?int $userId,
        string $reason,
        ?string $identifier,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $this->audit->recordAuthentication(
            userId: $userId,
            action: 'INICIO_SESION_FALLIDO',
            result: 'FALLIDO',
            reason: $reason,
            identifier: $identifier,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }

    private function clearSensitiveString(
        string &$value
    ): void {
        if (
            $value !== ''
            && function_exists('sodium_memzero')
        ) {
            sodium_memzero($value);
        }

        $value = '';
    }
}