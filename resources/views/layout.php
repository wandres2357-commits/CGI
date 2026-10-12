<?php

declare(strict_types=1);

use App\Auth\SessionManager;
use App\Http\View;
use App\Security\Csrf;

$pageTitle = isset($title)
    ? (string) $title
    : 'Inventario TI';

$user = SessionManager::user();
$flashMessages = SessionManager::consumeFlash();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="referrer"
        content="same-origin"
    >
    <title><?= View::escape($pageTitle) ?></title>
    <link
        rel="stylesheet"
        href="/assets/css/app.css"
    >
</head>
<body>
    <header class="site-header">
        <div class="container header-content">
            <a
                class="brand"
                href="<?=
                    $user !== null
                        ? '/panel'
                        : '/login'
                ?>"
            >
                Inventario TI
            </a>

            <?php if ($user !== null): ?>
                <nav class="user-navigation">
                    <span>
                        <?= View::escape(
                            $user['nombre_completo']
                        ) ?>
                    </span>

                    <form method="post" action="/logout">
                        <input
                            type="hidden"
                            name="_token"
                            value="<?= View::escape(
                                Csrf::generate('logout')
                            ) ?>"
                        >

                        <button
                            class="link-button"
                            type="submit"
                        >
                            Cerrar sesion
                        </button>
                    </form>
                </nav>
            <?php endif; ?>
        </div>
    </header>

    <main class="container main-content">
        <?php foreach ($flashMessages as $message): ?>
            <div
                class="alert alert-<?=
                    View::escape(
                        $message['type'] ?? 'info'
                    )
                ?>"
                role="alert"
            >
                <?= View::escape(
                    $message['message'] ?? ''
                ) ?>
            </div>
        <?php endforeach; ?>

        <?= $content ?>
    </main>
</body>
</html>
