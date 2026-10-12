<?php

declare(strict_types=1);

use App\Http\View;
use App\Security\Csrf;

$identifier = isset($identifier)
    ? (string) $identifier
    : '';

?>
<section class="auth-card">
    <h1>Iniciar sesion</h1>

    <p class="muted">
        Ingrese con su usuario o correo institucional.
    </p>

    <form method="post" action="/login">
        <input
            type="hidden"
            name="_token"
            value="<?= View::escape(
                Csrf::generate('login')
            ) ?>"
        >

        <div class="field">
            <label for="identifier">
                Usuario o correo institucional
            </label>

            <input
                id="identifier"
                name="identifier"
                type="text"
                value="<?= View::escape(
                    $identifier
                ) ?>"
                maxlength="254"
                autocomplete="username"
                required
                autofocus
            >
        </div>

        <div class="field">
            <label for="password">
                Contrase&ntilde;a
            </label>

            <input
                id="password"
                name="password"
                type="password"
                maxlength="128"
                autocomplete="current-password"
                required
            >
        </div>

        <button
            class="primary-button"
            type="submit"
        >
            Ingresar
        </button>
    </form>
</section>
