<?php

declare(strict_types=1);

use App\Auth\PasswordService;
use App\Database\Connection;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'bootstrap'
    . DIRECTORY_SEPARATOR
    . 'bootstrap.php';

$pdo = Connection::get();

$service = new PasswordService(
    pdo: $pdo,
    users: new UserRepository($pdo),
    sessions: new SessionRepository($pdo),
    audit: new AuditService($pdo)
);

$invalidCases = [
    'corta',
    'solominusculas123!',
    'SOLOMAYUSCULAS123!',
    'SinNumeroEspecial!',
    'SinCaracterEspecial123',
];

foreach ($invalidCases as $password) {
    if (
        $service->validateNewPassword(
            $password
        ) === []
    ) {
        fwrite(
            STDERR,
            'ERROR: una contraseña inválida '
            . 'fue aceptada.'
            . PHP_EOL
        );

        exit(1);
    }
}

$validPassword = 'TemporalSegura9!';

if (
    $service->validateNewPassword(
        $validPassword
    ) !== []
) {
    fwrite(
        STDERR,
        'ERROR: una contraseña válida '
        . 'fue rechazada.'
        . PHP_EOL
    );

    exit(1);
}

echo 'Longitud mínima: correcta.'
    . PHP_EOL;

echo 'Mayúscula requerida: correcta.'
    . PHP_EOL;

echo 'Minúscula requerida: correcta.'
    . PHP_EOL;

echo 'Número requerido: correcto.'
    . PHP_EOL;

echo 'Carácter especial requerido: correcto.'
    . PHP_EOL;

echo 'Contraseña válida aceptada: correcto.'
    . PHP_EOL;