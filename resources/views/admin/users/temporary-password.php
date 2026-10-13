<?php

declare(strict_types=1);

use App\Http\View;

$password = (string) (
    $temporaryData['password'] ?? ''
);

?>
<section class="secret-card">
    <h1>Contraseña temporal generada</h1>

    <div
        class="alert alert-warning"
        role="alert"
    >
        Copie esta contraseña ahora.
        Por seguridad, no volverá a mostrarse.
    </div>

    <dl class="summary-list">
        <div>
            <dt>Nombre</dt>
            <dd>
                <?= View::escape(
                    $temporaryData[
                        'full_name'
                    ] ?? ''
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Usuario</dt>
            <dd>
                <?= View::escape(
                    $temporaryData[
                        'username'
                    ] ?? ''
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Rol</dt>
            <dd>
                <?= View::escape(
                    $temporaryData[
                        'role'
                    ] ?? ''
                ) ?>
            </dd>
        </div>
    </dl>

    <div class="secret-display">
        <label for="temporary_password">
            Contraseña temporal
        </label>

        <input
            id="temporary_password"
            type="text"
            value="<?= View::escape(
                $password
            ) ?>"
            readonly
            autocomplete="off"
            spellcheck="false"
        >

        <p class="muted">
            Seleccione el contenido y use
            Ctrl + C para copiarlo.
        </p>
    </div>

    <a class="secondary-button" href="/administracion/usuarios">
        Volver a usuarios
    </a>
</section>