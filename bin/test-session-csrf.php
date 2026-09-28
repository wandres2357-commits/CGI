<?php

declare(strict_types=1);

use App\Auth\SessionManager;
use App\Security\Csrf;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'bootstrap'
    . DIRECTORY_SEPARATOR
    . 'bootstrap.php';

SessionManager::start();

$token = Csrf::generate(
    'test_session'
);

if (strlen($token) !== 64) {
    fwrite(
        STDERR,
        'ERROR: longitud de token incorrecta.'
        . PHP_EOL
    );

    exit(1);
}

if (!Csrf::validate('test_session', $token)) {
    fwrite(
        STDERR,
        'ERROR: el token válido fue rechazado.'
        . PHP_EOL
    );

    exit(1);
}

if (
    Csrf::validate(
        'test_session',
        $token
    )
) {
    fwrite(
        STDERR,
        'ERROR: el token fue reutilizado.'
        . PHP_EOL
    );

    exit(1);
}

if (
    Csrf::validate(
        'test_session',
        str_repeat('0', 64)
    )
) {
    fwrite(
        STDERR,
        'ERROR: un token falso fue aceptado.'
        . PHP_EOL
    );

    exit(1);
}

$secondToken = Csrf::generate(
    'test_session'
);

if (
    hash_equals(
        $token,
        $secondToken
    )
) {
    fwrite(
        STDERR,
        'ERROR: se generaron tokens iguales.'
        . PHP_EOL
    );

    exit(1);
}

echo 'Sesión iniciada correctamente.'
    . PHP_EOL;

echo 'Nombre de sesión: '
    . session_name()
    . PHP_EOL;

$sessionId = SessionManager::id();

if ($sessionId === '') {
    fwrite(
        STDERR,
        'ERROR: no se generó un identificador de sesión.'
        . PHP_EOL
    );

    exit(1);
}

echo 'Identificador de sesión generado: correcto.'
    . PHP_EOL;

echo 'Token CSRF generado: correcto.'
    . PHP_EOL;

echo 'Token CSRF de un solo uso: correcto.'
    . PHP_EOL;

echo 'Token CSRF falso rechazado: correcto.'
    . PHP_EOL;

SessionManager::destroy();

echo 'Sesión destruida correctamente.'
    . PHP_EOL;