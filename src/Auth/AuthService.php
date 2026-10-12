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

        if (
            $identifier === ''
            || $password === ''
        ) {
            $this->audit->recordAuthentication(
                userId: null,
                action: 'INICIO_SESION_FALLIDO',
                result: 'FALLIDO',
                reason: 'CREDENCIALES_INCOMPLETAS',
                identifier: $identifier !== ''
                    ? $identifier
                    : null,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            return AuthResult::failure();
        }

        $user = $this->users
            ->findByIdentifier($identifier);

        if ($user === null) {
            password_verify(
                $password,
                $this->dummyPasswordHash()
            );

            $this->audit->recordAuthentication(
                userId: null,
                action: 'INICIO_SESION_FALLIDO',
                result: 'FALLIDO',
                reason: 'CREDENCIALES_INVALIDAS',
                identifier: $identifier,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            return AuthResult::failure();
        }

        if (!$user['activo']) {
            $this->audit->recordAuthentication(
                userId: $user['id'],
                action: 'INICIO_SESION_FALLIDO',
                result: 'FALLIDO',
                reason: 'CUENTA_INACTIVA',
                identifier: $identifier,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            return AuthResult::failure();
        }

        $user = $this->clearExpiredBlock(
            $user
        );

        if ($this->isBlocked($user)) {
            $this->audit->recordAuthentication(
                userId: $user['id'],
                action: 'INICIO_SESION_FALLIDO',
                result: 'FALLIDO',
                reason: 'CUENTA_BLOQUEADA',
                identifier: $identifier,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            return AuthResult::failure(
                'ACCOUNT_BLOCKED'
            );
        }

        if (
            !password_verify(
                $password,
                $user['password_hash']
            )
        ) {
            $this->registerFailedAttempt(
                user: $user,
                identifier: $identifier,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            return AuthResult::failure();
        }

        return $this->completeSuccessfulLogin(
            user: $user,
            password: $password,
            identifier: $identifier,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }

    public function logout(
        string $reason = 'CIERRE_USUARIO',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        SessionManager::start();

        $user = SessionManager::user();
        $sessionId = SessionManager::id();

        if ($user !== null) {
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
        }

        SessionManager::destroy();
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
            $attempts =
                $this->users
                    ->incrementFailedAttempts(
                        $user['id']
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
                    $user['id'],
                    $blockedUntil
                );

                $reason = 'CUENTA_BLOQUEADA';

                $this->audit->recordAuthentication(
                    userId: $user['id'],
                    action: 'BLOQUEO_CUENTA',
                    result: 'EXITOSO',
                    reason: 'MAXIMO_INTENTOS_FALLIDOS',
                    identifier: $identifier,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent
                );
            }

            $this->audit->recordAuthentication(
                userId: $user['id'],
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

        $this->pdo->beginTransaction();

        try {
            if (
                password_needs_rehash(
                    $user['password_hash'],
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
                    userId: $user['id'],
                    passwordHash: $newHash,
                    requireChange:
                        $user['debe_cambiar_password']
                );
            }

            $this->users->recordSuccessfulLogin(
                $user['id']
            );

            SessionManager::authenticate(
                $user
            );

            $sessionId = SessionManager::id();

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
                userId: $user['id'],
                expiresAt: $expiresAt,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $closedSessions =
                $this->sessions
                    ->closeOldestUntilLimit(
                        userId: $user['id'],
                        maximumSessions:
                            $maximumSessions,
                        currentSessionId:
                            $sessionId
                    );

            $this->audit->recordAuthentication(
                userId: $user['id'],
                action: 'INICIO_SESION',
                result: 'EXITOSO',
                reason: 'CREDENCIALES_VALIDAS',
                identifier: $identifier,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            if ($closedSessions !== []) {
                $this->audit->record(
                    userId: $user['id'],
                    module: 'AUTENTICACION',
                    action:
                        'CIERRE_SESIONES_ANTIGUAS',
                    result: 'EXITOSO',
                    entityType: 'USUARIO',
                    entityId: $user['id'],
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

            $freshUser = $this->users
                ->findById($user['id']);

            if ($freshUser === null) {
                throw new RuntimeException(
                    'No fue posible recuperar '
                    . 'el usuario autenticado.'
                );
            }

            if (function_exists('sodium_memzero')) {
                sodium_memzero($password);
            }

            return AuthResult::success(
                $freshUser
            );
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            SessionManager::destroy();

            throw $exception;
        }
    }

    private function clearExpiredBlock(
        array $user
    ): array {
        if ($user['bloqueado_hasta'] === null) {
            return $user;
        }

        $blockedUntil = new DateTimeImmutable(
            $user['bloqueado_hasta']
        );

        if (
            $blockedUntil
            > new DateTimeImmutable()
        ) {
            return $user;
        }

        $this->users->clearExpiredBlock(
            $user['id']
        );

        $freshUser = $this->users
            ->findById($user['id']);

        return $freshUser ?? $user;
    }

    private function isBlocked(
        array $user
    ): bool {
        if ($user['bloqueado_hasta'] === null) {
            return false;
        }

        return new DateTimeImmutable(
            $user['bloqueado_hasta']
        ) > new DateTimeImmutable();
    }

    private function dummyPasswordHash(): string
    {
        return '$2y$12$abcdefghijklmnopqrstuu'
            . '7QyVjfnqY9rZkBvUwPsPV7bRwg9yK';
    }
}