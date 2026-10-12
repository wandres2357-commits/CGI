<?php

declare(strict_types=1);

use App\Auth\SessionManager;
use App\Http\View;

$items = is_array($result['items'] ?? null)
    ? $result['items']
    : [];

$pagination = is_array(
    $result['pagination'] ?? null
)
    ? $result['pagination']
    : [];

$filters = is_array(
    $result['filters'] ?? null
)
    ? $result['filters']
    : [];

$currentUserId = SessionManager::userId();

$buildPageUrl = static function (
    int $page
) use (
    $filters,
    $pagination
): string {
    $parameters = [
        'q' => (string) (
            $filters['q'] ?? ''
        ),
        'rol' => (string) (
            $filters['role'] ?? ''
        ),
        'estado' => (string) (
            $filters['state'] ?? ''
        ),
        'por_pagina' => (int) (
            $pagination['per_page'] ?? 20
        ),
        'pagina' => max(1, $page),
    ];

    $parameters = array_filter(
        $parameters,
        static fn (
            mixed $value
        ): bool => (
            $value !== ''
            && $value !== null
        )
    );

    return '/administracion/usuarios?'
        . http_build_query($parameters);
};

?>
<section>
    <div class="page-heading">
        <div>
            <h1>Administracion de usuarios</h1>

            <p class="muted">
                Gestione cuentas, roles y estados.
            </p>
        </div>

        <a
            class="button-link"
            href="/administracion/usuarios/crear"
        >
            Crear usuario
        </a>
    </div>

    <form
        class="filter-panel"
        method="get"
        action="/administracion/usuarios"
    >
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
                <option value="">Todos</option>

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

            <select id="estado" name="estado">
                <option value="">Todos</option>

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
                Por pagina
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
                                $pagination['per_page']
                                ?? 20
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

            <a
                class="secondary-button"
                href="/administracion/usuarios"
            >
                Limpiar
            </a>
        </div>
    </form>

    <p class="result-summary">
        Mostrando
        <?= (int) (
            $pagination['first_item'] ?? 0
        ) ?>
        a
        <?= (int) (
            $pagination['last_item'] ?? 0
        ) ?>
        de
        <?= (int) (
            $pagination['total'] ?? 0
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
                        <th>Ultimo acceso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($items as $user): ?>
                        <tr>
                            <td>
                                <?= View::escape(
                                    $user['nombre_completo']
                                ) ?>
                            </td>

                            <td>
                                <?= View::escape(
                                    $user['nombre_usuario']
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
                                    $user['rol_nombre']
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
                                    $user['esta_bloqueado']
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
                                    $user['ultimo_acceso_en']
                                    ?? 'Nunca'
                                ) ?>
                            </td>

                            T]]td>
                                <span class="muted">
                                    Disponible en 6.3B
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (
            (int) (
                $pagination['total_pages'] ?? 1
            ) > 1
        ): ?>
            <nav
                class="pagination"
                aria-label="Paginacion"
            >
                <?php if (
                    $pagination['has_previous']
                ): ?>
                    <a
                        class="secondary-button"
                        href="<?= View::escape(
                            $buildPageUrl(
                                (int) $pagination[
                                    'previous_page'
                                ]
                            )
                        ) ?>"
                    >
                        Anterior
                    </a>
                <?php endif; ?>

                <span>
                    Pagina
                    <?= (int) (
                        $pagination['page'] ?? 1
                    ) ?>
                    de
                    <?= (int) (
                        $pagination['total_pages']
                        ?? 1
                    ) ?>
                </span>

                <?php if (
                    $pagination['has_next']
                ): ?>
                    <a
                        class="secondary-button"
                        href="<?= View::escape(
                            $buildPageUrl(
                                (int) $pagination[
                                    'next_page'
                                ]
                            )
                        ) ?>"
                    >
                        Siguiente
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
