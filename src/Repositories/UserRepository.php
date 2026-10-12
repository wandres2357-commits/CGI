<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class UserRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findByIdentifier(
        string $identifier
    ): ?array {
        $identifier = strtolower(
            trim($identifier)
        );

        if ($identifier === '') {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT
                u.id,
                u.rol_id,
                u.nombre_usuario,
                u.nombre_completo,
                u.correo,
                u.password_hash,
                u.activo,
                u.debe_cambiar_password,
                u.intentos_fallidos,
                u.bloqueado_hasta,
                u.ultimo_acceso_en,
                r.codigo AS rol
             FROM usuarios u
             INNER JOIN roles r
                 ON r.id = u.rol_id
             WHERE (
                 u.nombre_usuario = :identificador_usuario
                 OR u.correo = :identificador_correo
             )
             LIMIT 1'
        );

        $statement->execute([
            'identificador_usuario' => $identifier,
            'identificador_correo' => $identifier,
        ]);

        $user = $statement->fetch();

        if (!is_array($user)) {
            return null;
        }

        return $this->normalizeUser($user);
    }

    public function findById(
        int $userId
    ): ?array {
        if ($userId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT
                u.id,
                u.rol_id,
                u.nombre_usuario,
                u.nombre_completo,
                u.correo,
                u.password_hash,
                u.activo,
                u.debe_cambiar_password,
                u.intentos_fallidos,
                u.bloqueado_hasta,
                u.ultimo_acceso_en,
                r.codigo AS rol
             FROM usuarios u
             INNER JOIN roles r
                 ON r.id = u.rol_id
             WHERE u.id = :id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $userId,
        ]);

        $user = $statement->fetch();

        if (!is_array($user)) {
            return null;
        }

        return $this->normalizeUser($user);
    }

    public function incrementFailedAttempts(
        int $userId
    ): int {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET intentos_fallidos =
                 intentos_fallidos + 1,
                 actualizado_en =
                 CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $userId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'No fue posible actualizar los intentos fallidos.'
            );
        }

        $countStatement = $this->pdo->prepare(
            'SELECT intentos_fallidos
             FROM usuarios
             WHERE id = :id'
        );

        $countStatement->execute([
            'id' => $userId,
        ]);

        $attempts = $countStatement->fetchColumn();

        if ($attempts === false) {
            throw new RuntimeException(
                'No fue posible consultar los intentos fallidos.'
            );
        }

        return (int) $attempts;
    }

    public function blockUntil(
        int $userId,
        string $blockedUntil
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET bloqueado_hasta = :bloqueado_hasta,
                 actualizado_en = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $statement->execute([
            'bloqueado_hasta' => $blockedUntil,
            'id' => $userId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'No fue posible bloquear temporalmente la cuenta.'
            );
        }
    }

    public function clearExpiredBlock(
        int $userId
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET intentos_fallidos = 0,
                 bloqueado_hasta = NULL,
                 actualizado_en = CURRENT_TIMESTAMP
             WHERE id = :id
               AND bloqueado_hasta IS NOT NULL
               AND bloqueado_hasta <= CURRENT_TIMESTAMP'
        );

        $statement->execute([
            'id' => $userId,
        ]);
    }

    public function recordSuccessfulLogin(
        int $userId
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET intentos_fallidos = 0,
                 bloqueado_hasta = NULL,
                 ultimo_acceso_en = CURRENT_TIMESTAMP,
                 actualizado_en = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $userId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'No fue posible registrar el acceso exitoso.'
            );
        }
    }

    public function updatePasswordHash(
        int $userId,
        string $passwordHash,
        bool $requireChange
    ): void {
        if ($passwordHash === '') {
            throw new RuntimeException(
                'El hash de contraseña no puede estar vacío.'
            );
        }

        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET password_hash = :password_hash,
                 debe_cambiar_password = :requiere_cambio,
                 intentos_fallidos = 0,
                 bloqueado_hasta = NULL,
                 actualizado_en = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $statement->execute([
            'password_hash' => $passwordHash,
            'requiere_cambio' => $requireChange ? 1 : 0,
            'id' => $userId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'No fue posible actualizar la contraseña.'
            );
        }
    }

    public function setActive(
        int $userId,
        bool $active
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET activo = :activo,
                 actualizado_en = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $statement->execute([
            'activo' => $active ? 1 : 0,
            'id' => $userId,
        ]);
    }

    private function normalizeUser(
        array $user
    ): array {
        return [
            'id' => (int) $user['id'],
            'rol_id' => (int) $user['rol_id'],
            'nombre_usuario' =>
                (string) $user['nombre_usuario'],
            'nombre_completo' =>
                (string) $user['nombre_completo'],
            'correo' => (string) $user['correo'],
            'password_hash' =>
                (string) $user['password_hash'],
            'activo' => (bool) $user['activo'],
            'debe_cambiar_password' =>
                (bool) $user['debe_cambiar_password'],
            'intentos_fallidos' =>
                (int) $user['intentos_fallidos'],
            'bloqueado_hasta' =>
                $user['bloqueado_hasta'] !== null
                    ? (string) $user['bloqueado_hasta']
                    : null,
            'ultimo_acceso_en' =>
                $user['ultimo_acceso_en'] !== null
                    ? (string) $user['ultimo_acceso_en']
                    : null,
            'rol' => (string) $user['rol'],
        ];
    }
}