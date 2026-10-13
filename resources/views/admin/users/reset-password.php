<?php

declare(strict_types=1);

use App\Http\View;
use App\Security\Csrf;

?>
<section class="form-page">
    <div class="page-heading">
        <div>
            <h1>
                Restablecer contrase&ntilde;a
            </h1>

            <p class="muted">
                Se generara una nueva contrase&ntilde;a
                temporal para esta cuenta.
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
                No fue posible restablecer
                la contrase&ntilde;a.
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

    <dl class="summary-list">
        <div>
            <dt>Nombre</dt>
            <dd>
                <?= View::escape(
                    $user['nombre_completo']
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Usuario</dt>
            <dd>
                <?= View::escape(
                    $user['nombre_usuario']
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Rol</dt>
            <dd>
                <?= View::escape(
                    $user['rol_nombre']
                ) ?>
            </dd>
        </div>
    </dl>

    <form
        class="admin-form"
        method="post"
        action="/administracion/usuarios/<?=
            (int) $user['id']
        ?>/restablecer-password"
    >
        <input
            type="hidden"
            name="_token"
            value="<?= View::escape(
                Csrf::generate(
                    'admin_reset_password_'
                    . (int) $user['id']
                )
            ) ?>"
        >

        <div class="alert alert-warning" role="alert">
            Esta operacion cerrara todas las sesiones
            activas del usuario y exigira un cambio
            de contrase&ntilde;a en el siguiente ingreso.
        </div>

        <div class="password-rules">
            <p>
                La nueva contrase&ntilde;a temporal:
            </p>

            <ul>
                <li>Tendra 20 caracteres.</li>
                <li>
                    Excluira caracteres ambiguos.
                </li>
                <li>
                    Se mostrara una sola vez.
                </li>
                <li>
                    No se almacenara en texto plano.
                </li>
            </ul>
        </div>

        <div class="field">
            <label for="actor_password">
                Su contrase&ntilde;a actual
                de administrador
            </label>

            <input
                id="actor_password"
                name="actor_password"
                type="password"
                maxlength="128"
                autocomplete="current-password"
                required
                autofocus
            >
        </div>

        <button
            class="danger-button"
            type="submit"
        >
            Generar nueva contrase&ntilde;a temporal
        </button>
    </form>
</section>
