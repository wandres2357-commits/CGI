<?php

declare(strict_types=1);

use App\Http\View;
use App\Security\Csrf;

$locationId = (int) ($location['id'] ?? 0);
?>
<section class="form-page">
    <div class="page-heading">
        <div>
            <h1>Editar ubicación</h1>
            <p class="muted">
                Actualice la sede, el tipo y la jerarquía. El servicio impedirá padres propios, sedes cruzadas y ciclos.
            </p>
        </div>
        <a class="secondary-button" href="/admin/ubicaciones">Volver</a>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert">
            <strong>No fue posible actualizar la ubicación.</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= View::escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="admin-form" method="post" action="/admin/ubicaciones/editar">
        <input type="hidden" name="_token" value="<?= View::escape(Csrf::generate('admin_edit_location_' . $locationId)) ?>">
        <input type="hidden" name="id" value="<?= $locationId ?>">

        <div class="field">
            <label for="sede_id">Sede</label>
            <select id="sede_id" name="sede_id" required>
                <option value="">Seleccione una sede</option>
                <?php foreach ($sites as $site): ?>
                    <option value="<?= (int) $site['id'] ?>" <?= (int) ($form['sede_id'] ?? 0) === (int) $site['id'] ? 'selected' : '' ?>>
                        <?= View::escape(($site['codigo'] ?? '') . ' - ' . ($site['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="tipo_id">Tipo de ubicación</label>
            <select id="tipo_id" name="tipo_id" required>
                <option value="">Seleccione un tipo</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?= (int) $type['id'] ?>" <?= (int) ($form['tipo_id'] ?? 0) === (int) $type['id'] ? 'selected' : '' ?>>
                        <?= View::escape($type['nombre'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="ubicacion_padre_id">Ubicación padre</label>
            <select
                id="ubicacion_padre_id"
                name="ubicacion_padre_id"
                data-selected-parent="<?= View::escape(
                    $form['ubicacion_padre_id']
                    ?? ''
                ) ?>"
                data-exclude-id="<?= $locationId ?>"
            >
                <option value="">Sin padre</option>
                <?php foreach ($parents as $parent): ?>
                    <option value="<?= (int) $parent['id'] ?>" <?= (int) ($form['ubicacion_padre_id'] ?? 0) === (int) $parent['id'] ? 'selected' : '' ?>>
                        <?= View::escape(($parent['codigo'] ?? '') . ' - ' . ($parent['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="muted">
                Solo se muestran ubicaciones activas de la sede seleccionada y se excluye la ubicación actual.
            </small>
        </div>

        <div class="field">
            <label for="codigo">Código</label>
            <input id="codigo" name="codigo" type="text" value="<?= View::escape($form['codigo'] ?? '') ?>" maxlength="50" autocomplete="off" required autofocus>
        </div>

        <div class="field">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" type="text" value="<?= View::escape($form['nombre'] ?? '') ?>" maxlength="160" required>
        </div>

        <div class="field">
            <label for="detalle">Detalle</label>
            <input id="detalle" name="detalle" type="text" value="<?= View::escape($form['detalle'] ?? '') ?>" maxlength="255">
        </div>

        <button class="primary-button" type="submit">Guardar cambios</button>
    </form>
</section>
