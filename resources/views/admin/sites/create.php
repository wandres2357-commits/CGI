<?php

declare(strict_types=1);

use App\Http\View;
use App\Security\Csrf;
?>
<section class="form-page">
    <div class="page-heading">
        <div>
            <h1>Crear sede</h1>
            <p class="muted">
                Registre una sede institucional. El código y el nombre deben ser únicos.
            </p>
        </div>
        <a class="secondary-button" href="/admin/sedes">Volver</a>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert">
            <strong>No fue posible crear la sede.</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= View::escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="admin-form" method="post" action="/admin/sedes/crear">
        <input type="hidden" name="_token" value="<?= View::escape(Csrf::generate('admin_create_site')) ?>">

        <div class="field">
            <label for="codigo">Código institucional</label>
            <input id="codigo" name="codigo" type="text" value="<?= View::escape($form['codigo'] ?? '') ?>" maxlength="30" autocomplete="off" required autofocus>
        </div>

        <div class="field">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" type="text" value="<?= View::escape($form['nombre'] ?? '') ?>" maxlength="120" required>
        </div>

        <div class="field">
            <label for="descripcion">Descripción</label>
            <input id="descripcion" name="descripcion" type="text" value="<?= View::escape($form['descripcion'] ?? '') ?>" maxlength="255">
        </div>

        <div class="field">
            <label for="direccion">Dirección</label>
            <input id="direccion" name="direccion" type="text" value="<?= View::escape($form['direccion'] ?? '') ?>" maxlength="255">
        </div>

        <div class="field">
            <label for="ciudad">Ciudad</label>
            <input id="ciudad" name="ciudad" type="text" value="<?= View::escape($form['ciudad'] ?? '') ?>" maxlength="120">
        </div>

        <div class="field">
            <label for="departamento">Departamento</label>
            <input id="departamento" name="departamento" type="text" value="<?= View::escape($form['departamento'] ?? '') ?>" maxlength="120">
        </div>

        <button class="primary-button" type="submit">Crear sede</button>
    </form>
</section>
