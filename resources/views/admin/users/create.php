<?php

declare(strict_types=1);

use App\Http\View;
use App\Security\Csrf;

$selectedRole =
    (string) ($form['rol'] ?? 'TECNICO');

?>
<section class="form-page">
    <div class="page-heading">
        <div>
            <h1>Crear usuario</h1>

            <p class="muted">
                El sistema generará una contraseña
                temporal de 20 caracteres.
            </p>
        </div>

        /administracion/usuarios
            Volver
        </a>
    </div>

    <?php if ($errors !== []): ?>
        <div
            class="alert alert-error"
            role="alert"
        >
            <strong>
                No fue posible crear el usuario.
            </strong>

            <ul>
                <?php foreach (
                    $errors
                    as $error
                ): ?>
                    <li>
                        <?= View::escape($error) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    /administracion/usuarios/crear
        <input
            type="hidden"
            name="_token"
            value="<?= View::escape(
                Csrf::generate(
                    'admin_create_user'
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
                    $form['nombre_completo']
                    ?? ''
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
                    $form['nombre_usuario']
                    ?? ''
                ) ?>"
                minlength="4"
                maxlength="100"
                pattern="[a-z0-9._-]+"
                autocomplete="off"
                required
            >

            <small class="muted">
                Use letras minúsculas, números,
                punto, guion o guion bajo.
            </small>
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
                autocomplete="off"
                required
            >
        </div>

        <div class="field">
            <label for="rol">Rol</label>

            <select
                id="rol"
                name="rol"
                required
            >
                <?php foreach (
                    $roles
                    as $role
                ): ?>
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
                Contraseña actual del administrador
            </label>

            <input
                id="actor_password"
                name="actor_password"
                type="password"
                maxlength="128"
                autocomplete="current-password"
            >

            <small class="muted">
                Es obligatoria al crear otra cuenta
                con rol Administrador.
            </small>
        </div>

        <div class="password-rules">
            <p>
                La contraseña temporal:
            </p>

            <ul>
                <li>Tendrá 20 caracteres.</li>
                <li>Excluirá caracteres ambiguos.</li>
                <li>Se mostrará una sola vez.</li>
                <li>
                    Deberá cambiarse en el primer ingreso.
                </li>
            </ul>
        </div>

        <button
            class="primary-button"
            type="submit"
        >
            Crear usuario
        </button>
    </form>
</section>