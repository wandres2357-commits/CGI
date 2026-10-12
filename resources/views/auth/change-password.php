<?php

declare(strict_types=1);

use App\Http\View;
use App\Security\Csrf;

?>
<section class="auth-card">
    <h1>Cambiar contrase&ntilde;a</h1>

    <p>
        Debe cambiar la contrase&ntilde;a temporal antes de
        acceder al panel.
    </p>

    <form method="post" action="/cambiar-password">
        <input
            type="hidden"
            name="_token"
            value="<?= View::escape(
                Csrf::generate('change_password')
            ) ?>"
        >

        <div class="field">
            <label for="current_password">
                Contrase&ntilde;a actual
            </label>

            <input
                id="current_password"
                name="current_password"
                type="password"
                maxlength="128"
                autocomplete="current-password"
                required
            >
        </div>

        <div class="field">
            <label for="new_password">
                Nueva contrase&ntilde;a
            </label>

            <input
                id="new_password"
                name="new_password"
                type="password"
                minlength="12"
                maxlength="128"
                autocomplete="new-password"
                required
            >
        </div>

        <div class="field">
            <label for="password_confirmation">
                Confirmar nueva contrase&ntilde;a
            </label>

            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                minlength="12"
                maxlength="128"
                autocomplete="new-password"
                required
            >
        </div>

        <button
            class="primary-button"
            type="submit"
        >
            Cambiar contrase&ntilde;a
        </button>
    </form>

    <p class="muted">
        El procesamiento seguro del cambio de contrase&ntilde;a
        se habilitara en la siguiente fase.
    </p>
</section>
