<?php

declare(strict_types=1);

use App\Http\View;
use App\Security\Csrf;

$locations = is_array($locations ?? null) ? $locations : [];
$sites = is_array($sites ?? null) ? $sites : [];
$types = is_array($types ?? null) ? $types : [];
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
        'sede_id' => (int) ($filters['sede_id'] ?? 0),
        'tipo_id' => (int) ($filters['tipo_id'] ?? 0),
        'estado' => (string) ($filters['estado'] ?? ''),
        'por_pagina' => (int) ($filters['por_pagina'] ?? 20),
        'pagina' => max(1, $targetPage),
    ];

    $parameters = array_filter(
        $parameters,
        static fn (mixed $value): bool => $value !== '' && $value !== null && $value !== 0
    );

    return '/admin/ubicaciones?' . http_build_query($parameters);
};
?>
<section>
    <div class="page-heading">
        <div>
            <h1>Administración de ubicaciones</h1>
            <p class="muted">
                Gestione espacios físicos, tipos y relaciones jerárquicas por sede.
            </p>
        </div>

        <a class="button-link" href="/admin/ubicaciones/crear">
            Crear ubicación
        </a>
    </div>

    <form class="filter-panel" method="get" action="/admin/ubicaciones">
        <div class="field">
            <label for="q">Buscar</label>
            <input
                id="q"
                name="q"
                type="search"
                value="<?= View::escape($filters['q'] ?? '') ?>"
                maxlength="255"
                placeholder="Código, nombre, detalle, sede o tipo"
            >
        </div>

        <div class="field">
            <label for="sede_id">Sede</label>
            <select id="sede_id" name="sede_id">
                <option value="">Todas</option>
                <?php foreach ($sites as $site): ?>
                    <option
                        value="<?= (int) $site['id'] ?>"
                        <?= (int) ($filters['sede_id'] ?? 0) === (int) $site['id'] ? 'selected' : '' ?>
                    >
                        <?= View::escape(($site['codigo'] ?? '') . ' - ' . ($site['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="tipo_id">Tipo</label>
            <select id="tipo_id" name="tipo_id">
                <option value="">Todos</option>
                <?php foreach ($types as $type): ?>
                    <option
                        value="<?= (int) $type['id'] ?>"
                        <?= (int) ($filters['tipo_id'] ?? 0) === (int) $type['id'] ? 'selected' : '' ?>
                    >
                        <?= View::escape($type['nombre'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
            <button class="primary-button auto-width" type="submit">Aplicar filtros</button>
            <a class="secondary-button" href="/admin/ubicaciones">Limpiar</a>
        </div>
    </form>

    <p class="result-summary">
        Mostrando <?= $firstItem ?> a <?= $lastItem ?> de <?= $total ?> ubicaciones.
    </p>

    <?php if ($locations === []): ?>
        <div class="empty-state">No se encontraron ubicaciones.</div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sede</th>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Ubicación padre</th>
                        <th>Hijas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $location): ?>
                        <tr>
                            <td><?= View::escape(($location['sede_codigo'] ?? '') . ' - ' . ($location['sede_nombre'] ?? '')) ?></td>
                            <td><?= View::escape($location['codigo'] ?? '') ?></td>
                            <td><?= View::escape($location['nombre'] ?? '') ?></td>
                            <td><?= View::escape($location['tipo_nombre'] ?? '') ?></td>
                            <td>
                                <?= View::escape($location['padre_nombre'] ?? 'Sin padre') ?>
                                <?php if (!empty($location['padre_codigo'])): ?>
                                    <span class="muted">(<?= View::escape($location['padre_codigo']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= (int) ($location['hijas_activas'] ?? 0) ?> activas
                                <span class="muted">de <?= (int) ($location['total_hijas'] ?? 0) ?></span>
                            </td>
                            <td>
                                <?php if ((bool) ($location['activo'] ?? false)): ?>
                                    <span class="status status-active">Activo</span>
                                <?php else: ?>
                                    <span class="status status-inactive">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="secondary-button" href="/admin/ubicaciones/editar?id=<?= (int) $location['id'] ?>">
                                    Editar
                                </a>
                                <form class="inline-action-form" method="post" action="/admin/ubicaciones/estado">
                                    <input type="hidden" name="_token" value="<?= View::escape(Csrf::generate('admin_location_state_' . (int) $location['id'])) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $location['id'] ?>">
                                    <input type="hidden" name="active" value="<?= (bool) $location['activo'] ? '0' : '1' ?>">
                                    <button class="link-button-dark" type="submit">
                                        <?= (bool) $location['activo'] ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Paginación de ubicaciones">
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
