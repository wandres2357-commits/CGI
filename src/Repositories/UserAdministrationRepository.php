<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class UserAdministrationRepository
{
    private const ALLOWED_PAGE_SIZES = [
        20,
        50,
        100,
    ];

    private const ALLOWED_ROLES = [
        'ADMINISTRADOR',
        'TECNICO',
    ];

    private const ALLOWED_STATES = [
        'activo',
        'inactivo',
        'bloqueado',
    ];

    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function paginate(
        string $search = '',
        ?string $role = null,
        ?string $state = null,
        int $page = 1,
        int $perPage = 20
    ): array {
        $search = trim($search);
        $role = $this->normalizeRoleFilter($role);
        $state = $this->normalizeStateFilter($state);
        $page = max(1, $page);

        if (
            !in_array(
                $perPage,
                self::ALLOWED_PAGE_SIZES,
                true
            )
        ) {
            $perPage = 20;
        }

        [
            'where' => $where,
            'parameters' => $parameters,
        ] = $this->buildFilters(
            search: $search,
            role: $role,
            state: $state
        );

        $countStatement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM usuarios u
             INNER JOIN roles r
                 ON r.id = u.rol_id'
            . $where
        );

        $countStatement->execute(
            $parameters
        );

        $total = (int) $countStatement
            ->fetchColumn();

        $totalPages = max(
            1,
            (int) ceil(
                $total / $perPage
            )
        );

        $page = min(
            $page,
            $totalPages
        );

        $offset = ($page - 1) * $perPage;

        $sql =
            'SELECT
                u.id,
                u.rol_id,
                u.nombre_usuario,
                u.nombre_completo,
                u.correo,
                u.activo,
                u.debe_cambiar_password,
                u.intentos_fallidos,
                u.bloqueado_hasta,
                u.ultimo_acceso_en,
                u.creado_en,
                u.actualizado_en,
                r.codigo AS rol_codigo,
                r.nombre AS rol_nombre,
                CASE
                    WHEN u.bloqueado_hasta IS NOT NULL
                     AND u.bloqueado_hasta > CURRENT_TIMESTAMP
                    THEN 1
                    ELSE 0
                END AS esta_bloqueado
             FROM usuarios u
             INNER JOIN roles r
                 ON r.id = u.rol_id'
            . $where
            . ' ORDER BY
                    u.nombre_completo ASC,
                    u.id ASC
                LIMIT '
            . (int) $perPage
            . ' OFFSET '
            . (int) $offset;

        $statement = $this->pdo->prepare(
            $sql
        );

        $statement->execute(
            $parameters
        );

        $items = [];

        while (
            $row = $statement->fetch()
        ) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = $this->normalizeUser(
                $row
            );
        }

        $firstItem = $total === 0
            ? 0
            : $offset + 1;

        $lastItem = $total === 0
            ? 0
            : min(
                $offset + $perPage,
                $total
            );

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'first_item' => $firstItem,
                'last_item' => $lastItem,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
                'previous_page' => $page > 1
                    ? $page - 1
                    : null,
                'next_page' => $page < $totalPages
                    ? $page + 1
                    : null,
            ],
            'filters' => [
                'q' => $search,
                'role' => $role,
                'state' => $state,
                'per_page' => $perPage,
            ],
        ];
    }

    public function findById(
        int $userId,
        bool $forUpdate = false
    ): ?array {
        if ($userId <= 0) {
            return null;
        }

        $sql =
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
                u.creado_en,
                u.actualizado_en,
                r.codigo AS rol_codigo,
                r.nombre AS rol_nombre,
                r.activo AS rol_activo
             FROM usuarios u
             INNER JOIN roles r
                 ON r.id = u.rol_id
             WHERE u.id = :id
             LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->pdo->prepare(
            $sql
        );

        $statement->execute([
            'id' => $userId,
        ]);

        $user = $statement->fetch();

        if (!is_array($user)) {
            return null;
        }

        return $this->normalizeUser(
            $user,
            includePasswordHash: true
        );
    }

    public function findRoleByCode(
        string $roleCode,
        bool $requireActive = true
    ): ?array {
        $roleCode = strtoupper(
            trim($roleCode)
        );

        if (
            !in_array(
                $roleCode,
                self::ALLOWED_ROLES,
                true
            )
        ) {
            return null;
        }

        $sql =
            'SELECT
                id,
                codigo,
                nombre,
                descripcion,
                activo
             FROM roles
             WHERE codigo = :codigo';

        if ($requireActive) {
            $sql .= ' AND activo = 1';
        }

        $sql .= ' LIMIT 1';

        $statement = $this->pdo->prepare(
            $sql
        );

        $statement->execute([
            'codigo' => $roleCode,
        ]);

        $role = $statement->fetch();

        if (!is_array($role)) {
            return null;
        }

        return [
            'id' => (int) $role['id'],
            'codigo' => (string) $role['codigo'],
            'nombre' => (string) $role['nombre'],
            'descripcion' =>
                $role['descripcion'] !== null
                    ? (string) $role['descripcion']
                    : null,
            'activo' => (bool) $role['activo'],
        ];
    }

    public function activeRoles(): array
    {
        $statement = $this->pdo->query(
            'SELECT
                id,
                codigo,
                nombre
             FROM roles
             WHERE activo = 1
             ORDER BY
                CASE codigo
                    WHEN \'ADMINISTRADOR\' THEN 1
                    WHEN \'TECNICO\' THEN 2
                    ELSE 3
                END,
                nombre ASC'
        );

        $roles = [];

        while (
            $row = $statement->fetch()
        ) {
            if (!is_array($row)) {
                continue;
            }

            $roles[] = [
                'id' => (int) $row['id'],
                'codigo' => (string) $row['codigo'],
                'nombre' => (string) $row['nombre'],
            ];
        }

        return $roles;
    }

    public function usernameExists(
        string $username,
        ?int $excludeUserId = null
    ): bool {
        $sql =
            'SELECT COUNT(*)
             FROM usuarios
             WHERE nombre_usuario =
                 :nombre_usuario';

        $parameters = [
            'nombre_usuario' =>
                strtolower(
                    trim($username)
                ),
        ];

        if (
            $excludeUserId !== null
            && $excludeUserId > 0
        ) {
            $sql .= ' AND id <> :id_excluido';

            $parameters['id_excluido'] =
                $excludeUserId;
        }

        $statement = $this->pdo->prepare(
            $sql
        );

        $statement->execute(
            $parameters
        );

        return (int) $statement
            ->fetchColumn() > 0;
    }

    public function emailExists(
        string $email,
        ?int $excludeUserId = null
    ): bool {
        $sql =
            'SELECT COUNT(*)
             FROM usuarios
             WHERE correo = :correo';

        $parameters = [
            'correo' => strtolower(
                trim($email)
            ),
        ];

        if (
            $excludeUserId !== null
            && $excludeUserId > 0
        ) {
            $sql .= ' AND id <> :id_excluido';

            $parameters['id_excluido'] =
                $excludeUserId;
        }

        $statement = $this->pdo->prepare(
            $sql
        );

        $statement->execute(
            $parameters
        );

        return (int) $statement
            ->fetchColumn() > 0;
    }

    public function create(
        int $roleId,
        string $username,
        string $fullName,
        string $email,
        string $passwordHash
    ): int {
        if (
            $roleId <= 0
            || $passwordHash === ''
        ) {
            throw new RuntimeException(
                'Los datos para crear el usuario '
                . 'no son válidos.'
            );
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO usuarios (
                rol_id,
                nombre_usuario,
                nombre_completo,
                correo,
                password_hash,
                activo,
                debe_cambiar_password,
                intentos_fallidos,
                bloqueado_hasta,
                ultimo_acceso_en,
                creado_en,
                actualizado_en
             ) VALUES (
                :rol_id,
                :nombre_usuario,
                :nombre_completo,
                :correo,
                :password_hash,
                1,
                1,
                0,
                NULL,
                NULL,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
             )'
        );

        $statement->execute([
            'rol_id' => $roleId,
            'nombre_usuario' =>
                strtolower(
                    trim($username)
                ),
            'nombre_completo' =>
                trim($fullName),
            'correo' => strtolower(
                trim($email)
            ),
            'password_hash' =>
                $passwordHash,
        ]);

        $userId = (int) $this->pdo
            ->lastInsertId();

        if ($userId <= 0) {
            throw new RuntimeException(
                'No fue posible recuperar '
                . 'el ID del usuario creado.'
            );
        }

        return $userId;
    }

    public function updateProfileAndRole(
        int $userId,
        int $roleId,
        string $username,
        string $fullName,
        string $email
    ): void {
        if ($userId <= 0 || $roleId <= 0) {
            throw new RuntimeException(
                'El usuario o el rol no son válidos.'
            );
        }

        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET rol_id = :rol_id,
                 nombre_usuario =
                    :nombre_usuario,
                 nombre_completo =
                    :nombre_completo,
                 correo = :correo,
                 actualizado_en =
                    CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $statement->execute([
            'rol_id' => $roleId,
            'nombre_usuario' =>
                strtolower(
                    trim($username)
                ),
            'nombre_completo' =>
                trim($fullName),
            'correo' => strtolower(
                trim($email)
            ),
            'id' => $userId,
        ]);
    }

    public function setActive(
        int $userId,
        bool $active
    ): void {
        if ($userId <= 0) {
            throw new RuntimeException(
                'El usuario no es válido.'
            );
        }

        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET activo = :activo,
                 intentos_fallidos =
                    CASE
                        WHEN :activo_reinicio = 1
                        THEN 0
                        ELSE intentos_fallidos
                    END,
                 bloqueado_hasta =
                    CASE
                        WHEN :activo_bloqueo = 1
                        THEN NULL
                        ELSE bloqueado_hasta
                    END,
                 actualizado_en =
                    CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $activeValue = $active ? 1 : 0;

        $statement->execute([
            'activo' => $activeValue,
            'activo_reinicio' =>
                $activeValue,
            'activo_bloqueo' =>
                $activeValue,
            'id' => $userId,
        ]);
    }

    public function resetPassword(
        int $userId,
        string $passwordHash
    ): void {
        if (
            $userId <= 0
            || $passwordHash === ''
        ) {
            throw new RuntimeException(
                'Los datos de restablecimiento '
                . 'no son válidos.'
            );
        }

        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET password_hash = :password_hash,
                 debe_cambiar_password = 1,
                 intentos_fallidos = 0,
                 bloqueado_hasta = NULL,
                 actualizado_en =
                    CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $statement->execute([
            'password_hash' => $passwordHash,
            'id' => $userId,
        ]);
    }

    public function countActiveAdministrators(
        bool $forUpdate = false
    ): int {
        $sql =
            'SELECT COUNT(*)
             FROM usuarios u
             INNER JOIN roles r
                 ON r.id = u.rol_id
             WHERE r.codigo =
                    \'ADMINISTRADOR\'
               AND r.activo = 1
               AND u.activo = 1';

        if ($forUpdate) {
            $sql =
                'SELECT u.id
                 FROM usuarios u
                 INNER JOIN roles r
                     ON r.id = u.rol_id
                 WHERE r.codigo =
                        \'ADMINISTRADOR\'
                   AND r.activo = 1
                   AND u.activo = 1
                 FOR UPDATE';

            $statement = $this->pdo->query(
                $sql
            );

            $ids = $statement->fetchAll(
                PDO::FETCH_COLUMN
            );

            return is_array($ids)
                ? count($ids)
                : 0;
        }

        return (int) $this->pdo
            ->query($sql)
            ->fetchColumn();
    }

    public function closeAllSessions(
        int $userId,
        string $reason
    ): int {
        if ($userId <= 0) {
            throw new RuntimeException(
                'El usuario no es válido.'
            );
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException(
                'El motivo de cierre es obligatorio.'
            );
        }

        $statement = $this->pdo->prepare(
            'UPDATE sesiones_usuario
             SET cerrada_en = CURRENT_TIMESTAMP,
                 motivo_cierre = :motivo
             WHERE usuario_id = :usuario_id
               AND cerrada_en IS NULL'
        );

        $statement->execute([
            'motivo' => mb_substr(
                $reason,
                0,
                100
            ),
            'usuario_id' => $userId,
        ]);

        return $statement->rowCount();
    }

    private function buildFilters(
        string $search,
        ?string $role,
        ?string $state
    ): array {
        $conditions = [];
        $parameters = [];

        if ($search !== '') {
            $conditions[] =
                '(u.nombre_usuario LIKE :busqueda
                  OR u.nombre_completo LIKE :busqueda
                  OR u.correo LIKE :busqueda)';

            $parameters['busqueda'] =
                '%' . $search . '%';
        }

        if ($role !== null) {
            $conditions[] =
                'r.codigo = :rol';

            $parameters['rol'] = $role;
        }

        if ($state === 'activo') {
            $conditions[] = 'u.activo = 1';
        } elseif ($state === 'inactivo') {
            $conditions[] = 'u.activo = 0';
        } elseif ($state === 'bloqueado') {
            $conditions[] =
                'u.activo = 1
                 AND u.bloqueado_hasta IS NOT NULL
                 AND u.bloqueado_hasta >
                     CURRENT_TIMESTAMP';
        }

        $where = $conditions === []
            ? ''
            : ' WHERE '
                . implode(
                    ' AND ',
                    $conditions
                );

        return [
            'where' => $where,
            'parameters' => $parameters,
        ];
    }

    private function normalizeRoleFilter(
        ?string $role
    ): ?string {
        if ($role === null) {
            return null;
        }

        $role = strtoupper(
            trim($role)
        );

        return in_array(
            $role,
            self::ALLOWED_ROLES,
            true
        )
            ? $role
            : null;
    }

    private function normalizeStateFilter(
        ?string $state
    ): ?string {
        if ($state === null) {
            return null;
        }

        $state = strtolower(
            trim($state)
        );

        return in_array(
            $state,
            self::ALLOWED_STATES,
            true
        )
            ? $state
            : null;
    }

    private function normalizeUser(
        array $user,
        bool $includePasswordHash = false
    ): array {
        $normalized = [
            'id' => (int) $user['id'],
            'rol_id' => (int) $user['rol_id'],
            'nombre_usuario' =>
                (string) $user['nombre_usuario'],
            'nombre_completo' =>
                (string) $user['nombre_completo'],
            'correo' => (string) $user['correo'],
            'activo' => (bool) $user['activo'],
            'debe_cambiar_password' =>
                (bool) $user[
                    'debe_cambiar_password'
                ],
            'intentos_fallidos' =>
                (int) $user['intentos_fallidos'],
            'bloqueado_hasta' =>
                $user['bloqueado_hasta'] !== null
                    ? (string) $user[
                        'bloqueado_hasta'
                    ]
                    : null,
            'ultimo_acceso_en' =>
                $user['ultimo_acceso_en'] !== null
                    ? (string) $user[
                        'ultimo_acceso_en'
                    ]
                    : null,
            'creado_en' =>
                (string) $user['creado_en'],
            'actualizado_en' =>
                (string) $user['actualizado_en'],
            'rol_codigo' =>
                (string) $user['rol_codigo'],
            'rol_nombre' =>
                (string) $user['rol_nombre'],
            'esta_bloqueado' =>
                isset($user['esta_bloqueado'])
                    ? (bool) $user[
                        'esta_bloqueado'
                    ]
                    : (
                        $user['bloqueado_hasta']
                        !== null
                    ),
        ];

        if ($includePasswordHash) {
            $normalized['password_hash'] =
                (string) $user['password_hash'];

            $normalized['rol_activo'] =
                isset($user['rol_activo'])
                    ? (bool) $user['rol_activo']
                    : true;
        }

        return $normalized;
    }
}