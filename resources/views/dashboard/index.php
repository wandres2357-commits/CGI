<?php

declare(strict_types=1);

use App\Auth\SessionManager;
use App\Http\View;

$user = SessionManager::user();

?>
<section>
    <h1>Panel de Inventario TI</h1>

    <p>
        Bienvenido,
        <strong>
            <?= View::escape(
                $user['nombre_completo'] ?? ''
            ) ?>
        </strong>.
    </p>

    <dl class="summary-list">
        <div>
            <dt>Usuario</dt>
            <dd>
                <?= View::escape(
                    $user['nombre_usuario'] ?? ''
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Correo</dt>
            <dd>
                <?= View::escape(
                    $user['correo'] ?? ''
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Rol</dt>
            <dd>
                <?= View::escape(
                    $user['rol'] ?? ''
                ) ?>
            </dd>
        </div>
    </dl>

    <?php if (
        SessionManager::hasRole(
            'ADMINISTRADOR'
        )
    ): ?>
        <p>
            /administracion/usuarios
                Administración de usuarios
            </a>
        </p>
    <?php endif; ?>

    <p class="muted">
        Los módulos de activos, solicitudes,
        entregas e informes se incorporarán en
        las siguientes fases.
    </p>
</section>