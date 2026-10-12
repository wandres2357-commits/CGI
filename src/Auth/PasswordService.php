<?php

declare(strict_types=1);

namespace App\Auth;

use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use PDO;
use RuntimeException;

final class PasswordService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly UserRepository $users,
        private readonly SessionRepository $sessions,
        private readonly AuditService $audit
    ) {
    }

    public function changeOwnPassword(
        int $userId,
        string $currentPassword,
        string $newPassword,
        string $confirmation,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): PasswordResult {
        if ($userId <= 0) {
            $this->clearSensitiveValues(
                $currentPassword,
                $newPassword,
                $confirmation
            );

            return PasswordResult::failure(
                code: 'INVALID_SESSION',
                message: 'La sesión no es válida.'
            );
        }

        $validationErrors = $this->validateNewPassword(
            $newPassword
        );

        if ($currentPassword === '') {
            $validationErrors[] =
                'La contraseña actual es obligatoria.';
        }

        if (!hash_equals($newPassword, $confirmation)) {
            $validationErrors[] =
                'La confirmación no coincide '
                . 'con la nueva contraseña.';
        }

        if ($validationErrors !== []) {
            $this->clearSensitiveValues(
                $currentPassword,
                $newPassword,
                $confirmation
            );

            return PasswordResult::failure(
                code: 'VALIDATION_FAILED',
                message:
                    'Revise los datos ingresados.',
                errors: $validationErrors
            );
        }

        $user = $this->users->findById($userId);

        if ($user === null || !$user['activo']) {
            $this->recordFailure(
                userId: $userId,
                reason: 'USUARIO_NO_DISPONIBLE',
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->clearSensitiveValues(
                $currentPassword,
                $newPassword,
                $confirmation
            );

            return PasswordResult::failure(
                code: 'USER_UNAVAILABLE',
                message:
                    'No fue posible cambiar la contraseña.'
            );
        }

        if (
            !password_verify(
                $currentPassword,
                (string) $user['password_hash']
            )
        ) {
            $this->recordFailure(
                userId: $userId,
                reason: 'PASSWORD_ACTUAL_INVALIDO',
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->clearSensitiveValues(
                $currentPassword,
                $newPassword,
                $confirmation
            );

            return PasswordResult::failure(
                code: 'CURRENT_PASSWORD_INVALID',
                message:
                    'La contraseña actual no es válida.'
            );
        }

        if (
            password_verify(
                $newPassword,
                (string) $user['password_hash']
            )
        ) {
            $this->clearSensitiveValues(
                $currentPassword,
                $newPassword,
                $confirmation
            );

            return PasswordResult::failure(
                code: 'PASSWORD_REUSED',
                message:
                    'La nueva contraseña debe ser diferente '
                    . 'de la contraseña actual.'
            );
        }

        $passwordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            $this->clearSensitiveValues(
                $currentPassword,
                $newPassword,
                $confirmation
            );

            throw new RuntimeException(
                'No fue posible generar el hash '
                . 'de la nueva contraseña.'
            );
        }

        SessionManager::start();

        $oldSessionId = SessionManager::id();

        if ($oldSessionId === '') {
            $this->clearSensitiveValues(
                $currentPassword,
                $newPassword,
                $confirmation,
                $passwordHash
            );

            return PasswordResult::failure(
                code: 'INVALID_SESSION',
                message: 'La sesión no es válida.'
            );
        }

        SessionManager::regenerate();

        $newSessionId = SessionManager::id();

        if (
            $newSessionId === ''
            || hash_equals(
                $oldSessionId,
                $newSessionId
            )
        ) {
            $this->clearSensitiveValues(
                $currentPassword,
                $newPassword,
                $confirmation,
                $passwordHash
            );

            throw new RuntimeException(
                'No fue posible rotar la sesión.'
            );
        }

        $this->pdo->beginTransaction();

        try {
            $this->users->updatePasswordHash(
                userId: $userId,
                passwordHash: $passwordHash,
                requireChange: false
            );

            $this->sessions->replaceSessionId(
                oldSessionId: $oldSessionId,
                newSessionId: $newSessionId,
                userId: $userId
            );

            $closedSessions =
                $this->sessions->closeOtherSessions(
                    userId: $userId,
                    currentSessionId: $newSessionId,
                    reason: 'CAMBIO_PASSWORD'
                );

            $this->audit->record(
                userId: $userId,
                module: 'AUTENTICACION',
                action: 'CAMBIAR_PASSWORD_PROPIO',
                result: 'EXITOSO',
                entityType: 'USUARIO',
                entityId: $userId,
                newValues: [
                    'debe_cambiar_password' => false,
                    'intentos_fallidos' => 0,
                    'bloqueo_eliminado' => true,
                    'sesiones_cerradas' => $closedSessions,
                    'sesion_regenerada' => true,
                ],
                reason:
                    (bool) $user['debe_cambiar_password']
                        ? 'CAMBIO_OBLIGATORIO'
                        : 'CAMBIO_VOLUNTARIO',
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            SessionManager::destroy();

            $this->clearSensitiveValues(
                $currentPassword,
                $newPassword,
                $confirmation,
                $passwordHash
            );

            throw $exception;
        }

        SessionManager::markPasswordChanged();

        $this->clearSensitiveValues(
            $currentPassword,
            $newPassword,
            $confirmation,
            $passwordHash
        );

        return PasswordResult::success();
    }

    public function validateNewPassword(
        string $password
    ): array {
        $errors = [];

        $length = strlen($password);

        if ($length < 12) {
            $errors[] =
                'La nueva contraseña debe tener '
                . 'al menos 12 caracteres.';
        }

        if ($length > 128) {
            $errors[] =
                'La nueva contraseña no puede superar '
                . '128 caracteres.';
        }

        if (
            preg_match(
                '/[a-z]/',
                $password
            ) !== 1
        ) {
            $errors[] =
                'Debe incluir una letra minúscula.';
        }

        if (
            preg_match(
                '/[A-Z]/',
                $password
            ) !== 1
        ) {
            $errors[] =
                'Debe incluir una letra mayúscula.';
        }

        if (
            preg_match(
                '/[0-9]/',
                $password
            ) !== 1
        ) {
            $errors[] =
                'Debe incluir un número.';
        }

        if (
            preg_match(
                '/[^a-zA-Z0-9]/',
                $password
            ) !== 1
        ) {
            $errors[] =
                'Debe incluir un carácter especial.';
        }

        return $errors;
    }

    private function recordFailure(
        int $userId,
        string $reason,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $this->audit->record(
            userId: $userId > 0
                ? $userId
                : null,
            module: 'AUTENTICACION',
            action: 'CAMBIAR_PASSWORD_PROPIO',
            result: 'FALLIDO',
            entityType: $userId > 0
                ? 'USUARIO'
                : null,
            entityId: $userId > 0
                ? $userId
                : null,
            reason: $reason,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }

    private function clearSensitiveValues(
        string &...$values
    ): void {
        foreach ($values as &$value) {
            if (
                $value !== ''
                && function_exists('sodium_memzero')
            ) {
                sodium_memzero($value);
            }

            $value = '';
        }

        unset($value);
    }
}