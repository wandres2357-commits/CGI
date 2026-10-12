<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserAdministrationRepository;
use App\Security\TemporaryPasswordGenerator;
use PDO;
use PDOException;
use RuntimeException;

final class UserAdministrationService
{
    private const ROLE_ADMINISTRATOR =
        'ADMINISTRADOR';

    private const ROLE_TECHNICIAN =
        'TECNICO';

    public function __construct(
        private readonly PDO $pdo,
        private readonly UserAdministrationRepository $repository,
        private readonly AuditService $audit,
        private readonly TemporaryPasswordGenerator $passwordGenerator
    ) {
    }

    public function listUsers(
        string $search = '',
        ?string $role = null,
        ?string $state = null,
        int $page = 1,
        int $perPage = 20
    ): array {
        return $this->repository->paginate(
            search: $search,
            role: $role,
            state: $state,
            page: $page,
            perPage: $perPage
        );
    }

    public function activeRoles(): array
    {
        return $this->repository
            ->activeRoles();
    }

    public function getUser(
        int $userId
    ): ?array {
        return $this->repository
            ->findById($userId);
    }

    public function createUser(
        int $actorUserId,
        string $username,
        string $fullName,
        string $email,
        string $roleCode,
        string $actorPassword = '',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $username = strtolower(
            trim($username)
        );

        $fullName = trim($fullName);

        $email = strtolower(
            trim($email)
        );

        $roleCode = strtoupper(
            trim($roleCode)
        );

        $errors = $this->validateProfile(
            username: $username,
            fullName: $fullName,
            email: $email,
            roleCode: $roleCode
        );

        if ($errors !== []) {
            $this->clearSensitiveValue(
                $actorPassword
            );

            return $this->failure(
                code: 'VALIDATION_FAILED',
                message:
                    'Revise los datos del usuario.',
                errors: $errors
            );
        }

        $actor = $this->requireAdministrator(
            $actorUserId
        );

        if (
            $roleCode
            === self::ROLE_ADMINISTRATOR
            && !$this->verifyActorPassword(
                $actor,
                $actorPassword
            )
        ) {
            $this->auditFailure(
                actorUserId: $actorUserId,
                action: 'CREAR_USUARIO',
                reason:
                    'CONFIRMACION_ADMIN_INVALIDA',
                entityId: null,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->clearSensitiveValue(
                $actorPassword
            );

            return $this->failure(
                code:
                    'ADMIN_PASSWORD_INVALID',
                message:
                    'La contraseña administrativa '
                    . 'no es válida.'
            );
        }

        $role = $this->repository
            ->findRoleByCode($roleCode);

        if ($role === null) {
            $this->clearSensitiveValue(
                $actorPassword
            );

            return $this->failure(
                code: 'ROLE_INVALID',
                message:
                    'El rol seleccionado no está disponible.'
            );
        }

        $temporaryPassword =
            $this->passwordGenerator
                ->generate();

        $passwordHash = password_hash(
            $temporaryPassword,
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            $this->clearSensitiveValue(
                $actorPassword
            );

            $this->clearSensitiveValue(
                $temporaryPassword
            );

            throw new RuntimeException(
                'No fue posible generar el hash '
                . 'de la contraseña temporal.'
            );
        }

        $this->pdo->beginTransaction();

        try {
            if (
                $this->repository
                    ->usernameExists($username)
            ) {
                $this->pdo->rollBack();

                $this->clearSensitiveValue(
                    $actorPassword
                );

                $this->clearSensitiveValue(
                    $temporaryPassword
                );

                $this->clearSensitiveValue(
                    $passwordHash
                );

                return $this->failure(
                    code: 'USERNAME_EXISTS',
                    message:
                        'El nombre de usuario ya está registrado.'
                );
            }

            if (
                $this->repository
                    ->emailExists($email)
            ) {
                $this->pdo->rollBack();

                $this->clearSensitiveValue(
                    $actorPassword
                );

                $this->clearSensitiveValue(
                    $temporaryPassword
                );

                $this->clearSensitiveValue(
                    $passwordHash
                );

                return $this->failure(
                    code: 'EMAIL_EXISTS',
                    message:
                        'El correo ya está registrado.'
                );
            }

            $userId = $this->repository->create(
                roleId: (int) $role['id'],
                username: $username,
                fullName: $fullName,
                email: $email,
                passwordHash: $passwordHash
            );

            $this->audit->record(
                userId: $actorUserId,
                module: 'USUARIOS',
                action: 'CREAR_USUARIO',
                result: 'EXITOSO',
                entityType: 'USUARIO',
                entityId: $userId,
                newValues: [
                    'nombre_usuario' => $username,
                    'nombre_completo' => $fullName,
                    'correo' => $email,
                    'rol' => $roleCode,
                    'activo' => true,
                    'debe_cambiar_password' => true,
                    'longitud_password_temporal' =>
                        TemporaryPasswordGenerator::DEFAULT_LENGTH,
                ],
                reason:
                    'CREACION_ADMINISTRATIVA',
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->clearSensitiveValue(
                $actorPassword
            );

            $this->clearSensitiveValue(
                $temporaryPassword
            );

            $this->clearSensitiveValue(
                $passwordHash
            );

            if (
                (string) $exception->getCode()
                === '23000'
            ) {
                return $this->failure(
                    code: 'DUPLICATE_USER',
                    message:
                        'El usuario o el correo '
                        . 'ya están registrados.'
                );
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->clearSensitiveValue(
                $actorPassword
            );

            $this->clearSensitiveValue(
                $temporaryPassword
            );

            $this->clearSensitiveValue(
                $passwordHash
            );

            throw $exception;
        }

        $this->clearSensitiveValue(
            $actorPassword
        );

        $this->clearSensitiveValue(
            $passwordHash
        );

        return [
            'successful' => true,
            'code' => 'USER_CREATED',
            'message' =>
                'El usuario fue creado correctamente.',
            'errors' => [],
            'user_id' => $userId,
            'temporary_password' =>
                $temporaryPassword,
        ];
    }

    public function updateUser(
        int $actorUserId,
        int $targetUserId,
        string $username,
        string $fullName,
        string $email,
        string $roleCode,
        string $actorPassword = '',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $username = strtolower(
            trim($username)
        );

        $fullName = trim($fullName);

        $email = strtolower(
            trim($email)
        );

        $roleCode = strtoupper(
            trim($roleCode)
        );

        $errors = $this->validateProfile(
            username: $username,
            fullName: $fullName,
            email: $email,
            roleCode: $roleCode
        );

        if ($errors !== []) {
            $this->clearSensitiveValue(
                $actorPassword
            );

            return $this->failure(
                code: 'VALIDATION_FAILED',
                message:
                    'Revise los datos del usuario.',
                errors: $errors
            );
        }

        $actor = $this->requireAdministrator(
            $actorUserId
        );

        $this->pdo->beginTransaction();

        try {
            $target = $this->repository
                ->findById(
                    $targetUserId,
                    true
                );

            if ($target === null) {
                $this->pdo->rollBack();

                $this->clearSensitiveValue(
                    $actorPassword
                );

                return $this->failure(
                    code: 'USER_NOT_FOUND',
                    message:
                        'El usuario solicitado no existe.'
                );
            }

            $newRole = $this->repository
                ->findRoleByCode($roleCode);

            if ($newRole === null) {
                $this->pdo->rollBack();

                $this->clearSensitiveValue(
                    $actorPassword
                );

                return $this->failure(
                    code: 'ROLE_INVALID',
                    message:
                        'El rol seleccionado no está disponible.'
                );
            }

            $isPromotion =
                $target['rol_codigo']
                    === self::ROLE_TECHNICIAN
                && $roleCode
                    === self::ROLE_ADMINISTRATOR;

                        if (
                $isPromotion
                && !$this->verifyActorPassword(
                    $actor,
                    $actorPassword
                )
            ) {
                $this->pdo->rollBack();

                $this->auditFailure(
                    actorUserId: $actorUserId,
                    action: 'EDITAR_USUARIO',
                    reason:
                        'CONFIRMACION_ADMIN_INVALIDA',
                    entityId: $targetUserId,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent
                );

                $this->clearSensitiveValue(
                    $actorPassword
                );

                return $this->failure(
                    code:
                        'ADMIN_PASSWORD_INVALID',
                    message:
                        'La contraseña administrativa '
                        . 'no es válida.'
                );
            }

            $isLastActiveAdministratorDemotion =
                $target['rol_codigo']
                    === self::ROLE_ADMINISTRATOR
                && $target['activo']
                && $roleCode
                    !== self::ROLE_ADMINISTRATOR;

            if (
                $isLastActiveAdministratorDemotion
                && $this->repository
                    ->countActiveAdministrators(true)
                    <= 1
            ) {
                $this->pdo->rollBack();

                $this->clearSensitiveValue(
                    $actorPassword
                );

                return $this->failure(
                    code:
                        'LAST_ADMINISTRATOR',
                    message:
                        'No se puede cambiar el rol '
                        . 'del último administrador activo.'
                );
            }

            if (
                $this->repository->usernameExists(
                    $username,
                    $targetUserId
                )
            ) {
                $this->pdo->rollBack();

                $this->clearSensitiveValue(
                    $actorPassword
                );

                return $this->failure(
                    code: 'USERNAME_EXISTS',
                    message:
                        'El nombre de usuario ya está registrado.'
                );
            }

            if (
                $this->repository->emailExists(
                    $email,
                    $targetUserId
                )
            ) {
                $this->pdo->rollBack();

                $this->clearSensitiveValue(
                    $actorPassword
                );

                return $this->failure(
                    code: 'EMAIL_EXISTS',
                    message:
                        'El correo ya está registrado.'
                );
            }

            $this->repository
                ->updateProfileAndRole(
                    userId: $targetUserId,
                    roleId:
                        (int) $newRole['id'],
                    username: $username,
                    fullName: $fullName,
                    email: $email
                );

            $this->audit->record(
                userId: $actorUserId,
                module: 'USUARIOS',
                action: 'EDITAR_USUARIO',
                result: 'EXITOSO',
                entityType: 'USUARIO',
                entityId: $targetUserId,
                oldValues: [
                    'nombre_usuario' =>
                        $target['nombre_usuario'],
                    'nombre_completo' =>
                        $target['nombre_completo'],
                    'correo' => $target['correo'],
                    'rol' => $target['rol_codigo'],
                ],
                newValues: [
                    'nombre_usuario' => $username,
                    'nombre_completo' => $fullName,
                    'correo' => $email,
                    'rol' => $roleCode,
                ],
                reason:
                    $isPromotion
                        ? 'PROMOCION_ADMINISTRADOR'
                        : 'ACTUALIZACION_ADMINISTRATIVA',
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->clearSensitiveValue(
                $actorPassword
            );

            if (
                (string) $exception->getCode()
                === '23000'
            ) {
                return $this->failure(
                    code: 'DUPLICATE_USER',
                    message:
                        'El usuario o el correo '
                        . 'ya están registrados.'
                );
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->clearSensitiveValue(
                $actorPassword
            );

            throw $exception;
        }

        $this->clearSensitiveValue(
            $actorPassword
        );

        return [
            'successful' => true,
            'code' => 'USER_UPDATED',
            'message' =>
                'El usuario fue actualizado correctamente.',
            'errors' => [],
            'user_id' => $targetUserId,
        ];
    }

    public function changeUserState(
        int $actorUserId,
        int $targetUserId,
        bool $active,
        string $actorPassword = '',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $actor = $this->requireAdministrator(
            $actorUserId
        );

        if (
            !$active
            && $actorUserId === $targetUserId
        ) {
            $this->clearSensitiveValue(
                $actorPassword
            );

            return $this->failure(
                code: 'SELF_DEACTIVATION',
                message:
                    'No puede desactivar su propia cuenta.'
            );
        }

        $this->pdo->beginTransaction();

        try {
            $target = $this->repository
                ->findById(
                    $targetUserId,
                    true
                );

            if ($target === null) {
                $this->pdo->rollBack();

                $this->clearSensitiveValue(
                    $actorPassword
                );

                return $this->failure(
                    code: 'USER_NOT_FOUND',
                    message:
                        'El usuario solicitado no existe.'
                );
            }

            $isAdministratorDeactivation =
                !$active
                && $target['rol_codigo']
                    === self::ROLE_ADMINISTRATOR;

                        if (
                $isAdministratorDeactivation
                && !$this->verifyActorPassword(
                    $actor,
                    $actorPassword
                )
            ) {
                $this->pdo->rollBack();

                $this->auditFailure(
                    actorUserId: $actorUserId,
                    action:
                        'CAMBIAR_ESTADO_USUARIO',
                    reason:
                        'CONFIRMACION_ADMIN_INVALIDA',
                    entityId: $targetUserId,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent
                );

                $this->clearSensitiveValue(
                    $actorPassword
                );

                return $this->failure(
                    code:
                        'ADMIN_PASSWORD_INVALID',
                    message:
                        'La contraseña administrativa '
                        . 'no es válida.'
                );
            }

            if (
                $isAdministratorDeactivation
                && $target['activo']
                && $this->repository
                    ->countActiveAdministrators(true)
                    <= 1
            ) {
                $this->pdo->rollBack();

                $this->clearSensitiveValue(
                    $actorPassword
                );

                return $this->failure(
                    code:
                        'LAST_ADMINISTRATOR',
                    message:
                        'No se puede desactivar '
                        . 'al último administrador activo.'
                );
            }

            $sessionsClosed = 0;

            if (!$active) {
                $sessionsClosed =
                    $this->repository
                        ->closeAllSessions(
                            $targetUserId,
                            'USUARIO_DESACTIVADO'
                        );
            }

            $this->repository->setActive(
                $targetUserId,
                $active
            );

            $this->audit->record(
                userId: $actorUserId,
                module: 'USUARIOS',
                action:
                    'CAMBIAR_ESTADO_USUARIO',
                result: 'EXITOSO',
                entityType: 'USUARIO',
                entityId: $targetUserId,
                oldValues: [
                    'activo' =>
                        (bool) $target['activo'],
                ],
                newValues: [
                    'activo' => $active,
                    'sesiones_cerradas' =>
                        $sessionsClosed,
                ],
                reason:
                    $active
                        ? 'ACTIVACION_ADMINISTRATIVA'
                        : 'DESACTIVACION_ADMINISTRATIVA',
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->clearSensitiveValue(
                $actorPassword
            );

            throw $exception;
        }

        $this->clearSensitiveValue(
            $actorPassword
        );

        return [
            'successful' => true,
            'code' => $active
                ? 'USER_ACTIVATED'
                : 'USER_DEACTIVATED',
            'message' => $active
                ? 'El usuario fue activado correctamente.'
                : 'El usuario fue desactivado correctamente.',
            'errors' => [],
            'user_id' => $targetUserId,
        ];
    }

    public function resetOtherUserPassword(
        int $actorUserId,
        int $targetUserId,
        string $actorPassword,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        if ($actorUserId === $targetUserId) {
            $this->clearSensitiveValue(
                $actorPassword
            );

            return $this->failure(
                code: 'SELF_PASSWORD_RESET',
                message:
                    'Para modificar su propia contraseña '
                    . 'utilice Cambiar mi contraseña.'
            );
        }

        $actor = $this->requireAdministrator(
            $actorUserId
        );

        if (
            !$this->verifyActorPassword(
                $actor,
                $actorPassword
            )
        ) {
            $this->auditFailure(
                actorUserId: $actorUserId,
                action:
                    'RESTABLECER_PASSWORD_USUARIO',
                reason:
                    'CONFIRMACION_ADMIN_INVALIDA',
                entityId: $targetUserId,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->clearSensitiveValue(
                $actorPassword
            );

            return $this->failure(
                code:
                    'ADMIN_PASSWORD_INVALID',
                message:
                    'La contraseña administrativa '
                    . 'no es válida.'
            );
        }

        $temporaryPassword =
            $this->passwordGenerator
                ->generate();

        $passwordHash = password_hash(
            $temporaryPassword,
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            $this->clearSensitiveValue(
                $actorPassword
            );

            $this->clearSensitiveValue(
                $temporaryPassword
            );

            throw new RuntimeException(
                'No fue posible generar el hash '
                . 'de la contraseña temporal.'
            );
        }

        $this->pdo->beginTransaction();

        try {
            $target = $this->repository
                ->findById(
                    $targetUserId,
                    true
                );

            if ($target === null) {
                $this->pdo->rollBack();

                $this->clearSensitiveValue(
                    $actorPassword
                );

                $this->clearSensitiveValue(
                    $temporaryPassword
                );

                $this->clearSensitiveValue(
                    $passwordHash
                );

                return $this->failure(
                    code: 'USER_NOT_FOUND',
                    message:
                        'El usuario solicitado no existe.'
                );
            }

            $this->repository->resetPassword(
                $targetUserId,
                $passwordHash
            );

            $sessionsClosed =
                $this->repository
                    ->closeAllSessions(
                        $targetUserId,
                        'RESTABLECIMIENTO_PASSWORD'
                    );

            $this->audit->record(
                userId: $actorUserId,
                module: 'USUARIOS',
                action:
                    'RESTABLECER_PASSWORD_USUARIO',
                result: 'EXITOSO',
                entityType: 'USUARIO',
                entityId: $targetUserId,
                newValues: [
                    'debe_cambiar_password' =>
                        true,
                    'intentos_fallidos' => 0,
                    'bloqueo_eliminado' => true,
                    'sesiones_cerradas' =>
                        $sessionsClosed,
                    'longitud_password_temporal' =>
                        TemporaryPasswordGenerator::DEFAULT_LENGTH,
                ],
                reason:
                    'RESTABLECIMIENTO_ADMINISTRATIVO',
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->clearSensitiveValue(
                $actorPassword
            );

            $this->clearSensitiveValue(
                $temporaryPassword
            );

            $this->clearSensitiveValue(
                $passwordHash
            );

            throw $exception;
        }

        $this->clearSensitiveValue(
            $actorPassword
        );

        $this->clearSensitiveValue(
            $passwordHash
        );

        return [
            'successful' => true,
            'code' => 'PASSWORD_RESET',
            'message' =>
                'La contraseña temporal fue generada correctamente.',
            'errors' => [],
            'user_id' => $targetUserId,
            'temporary_password' =>
                $temporaryPassword,
        ];
    }

    private function requireAdministrator(
        int $actorUserId
    ): array {
        $actor = $this->repository
            ->findById($actorUserId);

        if (
            $actor === null
            || !$actor['activo']
            || $actor['rol_codigo']
                !== self::ROLE_ADMINISTRATOR
        ) {
            throw new RuntimeException(
                'La cuenta autenticada no tiene '
                . 'autorización administrativa.'
            );
        }

        return $actor;
    }

    private function verifyActorPassword(
        array $actor,
        string $password
    ): bool {
        if ($password === '') {
            return false;
        }

        return password_verify(
            $password,
            (string) $actor['password_hash']
        );
    }

    private function validateProfile(
        string $username,
        string $fullName,
        string $email,
        string $roleCode
    ): array {
        $errors = [];

        $usernameLength = strlen(
            $username
        );

        if (
            $usernameLength < 4
            || $usernameLength > 100
        ) {
            $errors[] =
                'El usuario debe tener entre '
                . '4 y 100 caracteres.';
        }

        if (
            preg_match(
                '/^[a-z0-9._-]+$/',
                $username
            ) !== 1
        ) {
            $errors[] =
                'El usuario solo puede contener '
                . 'letras minúsculas, números, '
                . 'punto, guion y guion bajo.';
        }

        $fullNameLength = mb_strlen(
            $fullName
        );

        if (
            $fullNameLength < 3
            || $fullNameLength > 200
        ) {
            $errors[] =
                'El nombre completo debe tener '
                . 'entre 3 y 200 caracteres.';
        }

        if (
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
            || strlen($email) > 254
        ) {
            $errors[] =
                'Ingrese un correo electrónico válido.';
        }

        if (
            !in_array(
                $roleCode,
                [
                    self::ROLE_ADMINISTRATOR,
                    self::ROLE_TECHNICIAN,
                ],
                true
            )
        ) {
            $errors[] =
                'El rol seleccionado no es válido.';
        }

        return $errors;
    }

    private function auditFailure(
        int $actorUserId,
        string $action,
        string $reason,
        ?int $entityId,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $this->audit->record(
            userId: $actorUserId > 0
                ? $actorUserId
                : null,
            module: 'USUARIOS',
            action: $action,
            result: 'FALLIDO',
            entityType: $entityId !== null
                ? 'USUARIO'
                : null,
            entityId: $entityId,
            reason: $reason,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }

    private function failure(
        string $code,
        string $message,
        array $errors = []
    ): array {
        return [
            'successful' => false,
            'code' => $code,
            'message' => $message,
            'errors' => $errors,
        ];
    }

    private function clearSensitiveValue(
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