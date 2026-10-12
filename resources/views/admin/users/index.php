<?php

declare(strict_types=1);

use App\Auth\SessionManager;
use App\Http\View;

$items = $result['items'] ?? [];
$pagination =
    $result['pagination'] ?? [];
$filters = $result['filters'] ?? [];

$currentUserId =
    SessionManager::userId();

function userAdminQuery(
    array $overrides = []
): string {
    $values = [
        'q' => $_GET['q'] ?? '',
        'rol' => $_GET['rol'] ?? '',
        'estado' =>
            $_GET['estado'] ?? '',
        'por_pagina' =>
            $_GET['por_pagina'] ?? 20,
        'pagina' =>
            $_GET['pagina'] ?? 1,
    ];

    foreach (
        $overrides
        as $key => $value
    ) {
        $values[$key] = $value;
    }

    $values = array_filter(
        $values,
        static fn (
            mixed $value
        ): bool => (
            $value !== ''
            && $value !== null
        )
    );

    return http_build_query($values);
}

?>
<section>
    <div class="page-heading">
        <div>
            <h1>Administración de usuarios</h1>

            <p class="muted">
                Gestione cuentas, roles y estados.
            </p>
        </div>

        /administracion/usuarios/crear
            Crear usuario
        </a>
    </div>

    /administracion/usuarios
        <div class="field">
            <label for="q">Buscar</label>

            <input
                id="q"
                name="q"
                type="search"
                value="<?= View::escape(
                    $filters['q'] ?? ''
                ) ?>"
                maxlength="254"
                placeholder="Nombre, usuario o correo"
            >
        </div>

        <div class="field">
            <label for="rol">Rol</label>

            <select id="rol" name="rol">
                <option value="">
                    Todos
                </option>

                <?php foreach ($roles as $role): ?>
                    <option
                        value="<?= View::escape(
                            $role['codigo']
                        ) ?>"
                        <?=
                            ($filters['role'] ?? '')
                            === $role['codigo']
                                ? 'selected'
                                : ''
                        ?>
                    >
                        <?= View::escape(
                            $role['nombre']
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="estado">Estado</label>

            <select
                id="estado"
                name="estado"
            >
                <option value="">
                    Todos
                </option>

                <option
                    value="activo"
                    <?=
                        ($filters['state'] ?? '')
                        === 'activo'
                            ? 'selected'
                            : ''
                    ?>
                >
                    Activo
                </option>

                <option
                    value="inactivo"
                    <?=
                        ($filters['state'] ?? '')
                        === 'inactivo'
                            ? 'selected'
                            : ''
                    ?>
                >
                    Inactivo
                </option>

                <option
                    value="bloqueado"
                    <?=
                        ($filters['state'] ?? '')
                        === 'bloqueado'
                            ? 'selected'
                            : ''
                    ?>
                >
                    Bloqueado
                </option>
            </select>
        </div>

        <div class="field">
            <label for="por_pagina">
                Por página
            </label>

            <select
                id="por_pagina"
                name="por_pagina"
            >
                <?php foreach (
                    [20, 50, 100]
                    as $pageSize
                ): ?>
                    <option
                        value="<?= $pageSize ?>"
                        <?=
                            (int) (
                                $pagination[
                                    'per_page'
                                ] ?? 20
                            ) === $pageSize
                                ? 'selected'
                                : ''
                        ?>
                    >
                        <?= $pageSize ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-actions">
            <button
                class="primary-button auto-width"
                type="submit"
            >
                Aplicar filtros
            </button>

            /administracion/usuarios
                Limpiar
            </a>
        </div>
    </form>

    <p class="result-summary">
        Mostrando
        <?= (int) (
            $pagination['first_item']
            ?? 0
        ) ?>
        a
        <?= (int) (
            $pagination['last_item']
            ?? 0
        ) ?>
        de
        <?= (int) (
            $pagination['total']
            ?? 0
        ) ?>
        usuarios.
    </p>

    <?php if ($items === []): ?>
        <div class="empty-state">
            No se encontraron usuarios.
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Último acceso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach (
                        $items
                        as $user
                    ): ?>
                        <tr>
                            <td>
                                <?= View::escape(
                                    $user[
                                        'nombre_completo'
                                    ]
                                ) ?>
                            </td>

                            <td>
                                <?= View::escape(
                                    $user[
                                        'nombre_usuario'
                                    ]
                                ) ?>

                                <?php if (
                                    (int) $user['id']
                                    === $currentUserId
                                ): ?>
                                    <span class="badge">
                                        Usted
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= View::escape(
                                    $user['correo']
                                ) ?>
                            </td>

                            <td>
                                <?= View::escape(
                                    $user[
                                        'rol_nombre'
                                    ]
                                ) ?>
                            </td>

                            <td>
                                <?php if (
                                    !$user['activo']
                                ): ?>
                                    <span
                                        class="status status-inactive"
                                    >
                                        Inactivo
                                    </span>
                                <?php elseif (
                                    $user[
                                        'esta_bloqueado'
                                    ]
                                ): ?>
                                    <span
                                        class="status status-blocked"
                                    >
                                        Bloqueado
                                    </span>
                                <?php else: ?>
                                    <span
                                        class="status status-active"
                                    >
                                        Activo
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= View::escape(
                                    $user[
                                        'ultimo_acceso_en'
                                    ] ?? 'Nunca'
                                ) ?>
                            </td>

                            <td>
                                <span class="muted">
                                    Edición disponible
                                    en la fase 6.3B
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (
            (int) (
                $pagination['total_pages']
                ?? 1
            ) > 1
        ): ?>
            <nav
                class="pagination"
                aria-label="Paginación"
            >
                <?php if (
                    $pagination[
                        'has_previous'
                    ]
                ): ?>
                                                        $pagination[
                                        'previous_page'
                                    ],
                            ])
                        ) ?>"
                    >
                        Anterior