<?php
declare(strict_types=1);

use App\Http\View;
use App\Security\Csrf;

$selectedRole = (string) (
    $form['rol'] ?? $user['rol_codigo']
);
?>
<section class="form-page">
    <div class="page-heading">
        <div>
            <h1>Editar usuario</h1>
            <p class="muted">
                Actualice los datos y el rol de la cuenta.
            </p>
        </div>

        <a
            class="secondary-button"
            href="/administracion/usuarios"
        >
            Volver
        </a>
    </div>

    <?php if ($errors !== []): ?>
        <div
            class="alert alert-error"
            role="alert"
        >
            <strong>
                No fue posible actualizar el usuario.
            </strong>

            <ul>
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= View::escape(
                            $error
                        ) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form
        class="admin-form"
        method="post"
        action="/administracion/usuarios/<?=
            (int) $user['id']
        ?>/editar"
    >
        <input
            type="hidden"
            name="_token"
            value="<?= View::escape(
                Csrf::generate(
                    'admin_edit_user_'
                    . (int) $user['id']
                )
            ) ?>"
        >

        <div class="field">
            <label for="nombre_completo">
                Nombre completo
            </label>

            <input
                id="nombre_completo"
                name="nombre_completo"
                type="text"
                value="<?= View::escape(
                    $form['nombre_completo'] ?? ''
                ) ?>"
                minlength="3"
                maxlength="200"
                required
                autofocus
            >
        </div>

        <div class="field">
            <label for="nombre_usuario">
                Nombre de usuario
            </label>

            <input
                id="nombre_usuario"
                name="nombre_usuario"
                type="text"
                value="<?= View::escape(
                    $form['nombre_usuario'] ?? ''
                ) ?>"
                minlength="4"
                maxlength="100"
                pattern="[a-z0-9._-]+"
                required
            >
        </div>

        <div class="field">
            <label for="correo">
                Correo institucional
            </label>

            <input
                id="correo"
                name="correo"
                type="email"
                value="<?= View::escape(
                    $form['correo'] ?? ''
                ) ?>"
                maxlength="254"
                required
            >
        </div>

        <div class="field">
            <label for="rol">Rol</label>

            <select id="rol" name="rol" required>
                <?php foreach ($roles as $role): ?>
                    <option
                        value="<?= View::escape(
                            $role['codigo']
                        ) ?>"
                        <?=
                            $selectedRole
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
            <label for="actor_password">
                Contrase&ntilde;a actual del administrador
            </label>

            <input
                id="actor_password"
                name="actor_password"
                type="password"
                maxlength="128"
                autocomplete="current-password"
            >

            <small class="muted">
                Es obligatoria al promover una cuenta
                Tecnico a Administrador.
            </small>
        </div>

        <button
            class="primary-button"
            type="submit"
        >
            Guardar cambios
        </button>
    </form>
</section>
