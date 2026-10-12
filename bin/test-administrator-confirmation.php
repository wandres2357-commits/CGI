<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\UserAdministrationRepository;
use App\Security\TemporaryPasswordGenerator;
use App\Services\AuditService;
use App\Services\UserAdministrationService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'bootstrap'
    . DIRECTORY_SEPARATOR
    . 'bootstrap.php';

function readHiddenAdministratorPassword(
    string $message
): string {
    fwrite(STDOUT, $message);

    $terminalState = trim(
        (string) shell_exec('stty -g')
    );

    if ($terminalState === '') {
        throw new RuntimeException(
            'No fue posible consultar '
            . 'el estado de la terminal.'
        );
    }

    shell_exec('stty -echo');

    try {
        $password = fgets(STDIN);

        fwrite(STDOUT, PHP_EOL);

        if ($password === false) {
            throw new RuntimeException(
                'No fue posible leer la contraseña.'
            );
        }

        return rtrim(
            $password,
            "\r\n"
        );
    } finally {
        shell_exec(
            'stty '
            . escapeshellarg(
                $terminalState
            )
        );
    }
}

$pdo = Connection::get();

$repository =
    new UserAdministrationRepository(
        $pdo
    );

$service =
    new UserAdministrationService(
        pdo: $pdo,
        repository: $repository,
        audit: new AuditService($pdo),
        passwordGenerator:
            new TemporaryPasswordGenerator()
    );

$password =
    readHiddenAdministratorPassword(
        'Contraseña actual del administrador: '
    );

$result = $service->createUser(
    actorUserId: 1,
    username: 'x',
    fullName: 'Prueba',
    email: 'correo-invalido',
    roleCode: 'ADMINISTRADOR',
    actorPassword: $password,
    ipAddress: '127.0.0.1',
    userAgent:
        'CLI test-administrator-confirmation.php'
);

if (
    $password !== ''
    && function_exists('sodium_memzero')
) {
    sodium_memzero($password);
}

$password = '';

if (
    $result['code']
    !== 'VALIDATION_FAILED'
) {
    fwrite(
        STDERR,
        'ERROR: la validación inicial '
        . 'no se comportó como se esperaba.'
        . PHP_EOL
    );

    exit(1);
}

$administrator =
    $repository->findById(
        1,
        true
    );

if (
    $administrator === null
    || !isset(
        $administrator['password_hash']
    )
    || $administrator['password_hash'] === ''
) {
    fwrite(
        STDERR,
        'ERROR: no se recuperó el hash '
        . 'administrativo internamente.'
        . PHP_EOL
    );

    exit(1);
}

echo 'Administrador activo localizado: correcto.'
    . PHP_EOL;

echo 'Rol ADMINISTRADOR validado: correcto.'
    . PHP_EOL;

echo 'Hash disponible solo en la capa interna: correcto.'
    . PHP_EOL;

echo 'Validación no destructiva aplicada: correcto.'
    . PHP_EOL;

echo 'No se creó ningún usuario de prueba.'
    . PHP_EOL;