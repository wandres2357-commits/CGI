<?php

declare(strict_types=1);

use App\Http\View;
use App\Security\Csrf;

$sites = is_array($sites ?? null) ? $sites : [];
$filters = is_array($filters ?? null) ? $filters : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];

$page = max(1, (int) ($pagination['page'] ?? 1));
$pages = max(1, (int) ($pagination['pages'] ?? 1));
$total = max(0, (int) ($pagination['total'] ?? 0));
$perPage = (int) ($filters['por_pagina'] ?? 20);
$firstItem = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$lastItem = $total === 0 ? 0 : min($total, $page * $perPage);

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $parameters = [
        'q' => (string) ($filters['q'] ?? ''),
        'estado' => (string) ($filters['estado'] ?? ''),
        'por_pagina' => (int) ($filters['por_pagina'] ?? 20),
        'pagina' => max(1, $targetPage),
    ];

    $parameters = array_filter(
        $parameters,
        static fn (mixed $value): bool => $value !== '' && $value !== null
    );

    return '/admin/sedes?' . http_build_query($parameters);
};
?>
<section>
    <div class="page-heading">
        <div>
            <h1>Administración de sedes</h1>
            <p class="muted">
                Gestione el catálogo institucional y su disponibilidad operativa.
            </p>
        </div>

        <a class="button-link" href="/admin/sedes/crear">
            Crear sede
        </a>
    </div>

    <form class="filter-panel" method="get" action="/admin/sedes">
        <div class="field">
            <label for="q">Buscar</label>
            <input
                id="q"
                name="q"
                type="search"
                value="<?= View::escape($filters['q'] ?? '') ?>"
                maxlength="255"
                placeholder="Código, nombre, ciudad o departamento"
            >
        </div>

        <div class="field">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <option value="">Todos</option>
                <option value="activo" <?= ($filters['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option>
                <option value="inactivo" <?= ($filters['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="field">
            <label for="por_pagina">Por página</label>
            <select id="por_pagina" name="por_pagina">
                <?php foreach ([20, 50, 100] as $pageSize): ?>
                    <option value="<?= $pageSize ?>" <?= $perPage === $pageSize ? 'selected' : '' ?>>
                        <?= $pageSize ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-actions">
            <button class="primary-button auto-width" type="submit">
                Aplicar filtros
            </button>
            <a class="secondary-button" href="/admin/sedes">Limpiar</a>
        </div>
    </form>

    <p class="result-summary">
        Mostrando <?= $firstItem ?> a <?= $lastItem ?> de <?= $total ?> sedes.
    </p>

    <?php if ($sites === []): ?>
        <div class="empty-state">No se encontraron sedes.</div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Ciudad</th>
                        <th>Departamento</th>
                        <th>Ubicaciones</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $site): ?>
                        <tr>
                            <td><?= View::escape($site['codigo'] ?? '') ?></td>
                            <td><?= View::escape($site['nombre'] ?? '') ?></td>
                            <td><?= View::escape($site['ciudad'] ?? 'Sin registrar') ?></td>
                            <td><?= View::escape($site['departamento'] ?? 'Sin registrar') ?></td>
                            <td>
                                <?= (int) ($site['ubicaciones_activas'] ?? 0) ?> activas
                                <span class="muted">
                                    de <?= (int) ($site['total_ubicaciones'] ?? 0) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ((bool) ($site['activo'] ?? false)): ?>
                                    <span class="status status-active">Activo</span>
                                <?php else: ?>
                                    <span class="status status-inactive">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="secondary-button" href="/admin/sedes/editar?id=<?= (int) $site['id'] ?>">
                                    Editar
                                </a>
                                <a class="secondary-button" href="/admin/ubicaciones?sede_id=<?= (int) $site['id'] ?>">
                                    Ubicaciones
                                </a>
                                <form class="inline-action-form" method="post" action="/admin/sedes/estado">
                                    <input type="hidden" name="_token" value="<?= View::escape(Csrf::generate('admin_site_state_' . (int) $site['id'])) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $site['id'] ?>">
                                    <input type="hidden" name="active" value="<?= (bool) $site['activo'] ? '0' : '1' ?>">
                                    <button class="link-button-dark" type="submit">
                                        <?= (bool) $site['activo'] ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Paginación de sedes">
                <?php if ($page > 1): ?>
                    <a class="secondary-button" href="<?= View::escape($buildPageUrl($page - 1)) ?>">Anterior</a>
                <?php endif; ?>
                <span>Página <?= $page ?> de <?= $pages ?></span>
                <?php if ($page < $pages): ?>
                    <a class="secondary-button" href="<?= View::escape($buildPageUrl($page + 1)) ?>">Siguiente</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
