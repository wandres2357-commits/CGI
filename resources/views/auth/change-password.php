<?php

declare(strict_types=1);

use App\Http\View;
use App\Security\Csrf;

?>
<section class="auth-card">
    <h1>Cambiar contraseña</h1>

    <p>
        Debe cambiar la contraseña temporal antes de
        acceder al panel.
    </p>

    /cambiar-password
        <input
            type="hidden"
            name="_token"
            value="<?= View::escape(
                Csrf::generate(
                    'change_password'
                )
            ) ?>"
        >

        <div class="field">
            <label for="current_password">
                Contraseña actual
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
                Nueva contraseña
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
                Confirmar nueva contraseña
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
            Cambiar contraseña
        </button>
    </form>

    <p class="muted">
        En esta fase el formulario está preparado.
        El procesamiento seguro se habilitará en el
        siguiente paso.
    </p>
</section>