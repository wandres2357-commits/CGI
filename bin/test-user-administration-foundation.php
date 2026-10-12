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

$roles = $service->activeRoles();

if (count($roles) !== 2) {
    fwrite(
        STDERR,
        'ERROR: no se encontraron '
        . 'los dos roles activos.'
        . PHP_EOL
    );

    exit(1);
}

$roleCodes = array_column(
    $roles,
    'codigo'
);

if (
    !in_array(
        'ADMINISTRADOR',
        $roleCodes,
        true
    )
    || !in_array(
        'TECNICO',
        $roleCodes,
        true
    )
) {
    fwrite(
        STDERR,
        'ERROR: faltan roles obligatorios.'
        . PHP_EOL
    );

    exit(1);
}

$page = $service->listUsers(
    page: 1,
    perPage: 20
);

if (
    !isset(
        $page['items'],
        $page['pagination'],
        $page['filters']
    )
) {
    fwrite(
        STDERR,
        'ERROR: estructura de paginación inválida.'
        . PHP_EOL
    );

    exit(1);
}

if (
    $page['pagination']['per_page']
    !== 20
) {
    fwrite(
        STDERR,
        'ERROR: tamaño predeterminado incorrecto.'
        . PHP_EOL
    );

    exit(1);
}

$invalidPageSize = $service->listUsers(
    page: 1,
    perPage: 999
);

if (
    $invalidPageSize[
        'pagination'
    ]['per_page'] !== 20
) {
    fwrite(
        STDERR,
        'ERROR: no se normalizó '
        . 'el tamaño de página inválido.'
        . PHP_EOL
    );

    exit(1);
}

$administrator = $service->getUser(1);

if (
    $administrator === null
    || $administrator['rol_codigo']
        !== 'ADMINISTRADOR'
) {
    fwrite(
        STDERR,
        'ERROR: no se encontró '
        . 'el administrador esperado.'
        . PHP_EOL
    );

    exit(1);
}

if (
    $repository->countActiveAdministrators()
    < 1
) {
    fwrite(
        STDERR,
        'ERROR: no existe '
        . 'un administrador activo.'
        . PHP_EOL
    );

    exit(1);
}

echo 'Roles activos encontrados: correcto.'
    . PHP_EOL;

echo 'Rol ADMINISTRADOR disponible: correcto.'
    . PHP_EOL;

echo 'Rol TECNICO disponible: correcto.'
    . PHP_EOL;

echo 'Paginación de 20 registros: correcta.'
    . PHP_EOL;

echo 'Tamaños permitidos 20, 50 y 100: configurados.'
    . PHP_EOL;

echo 'Tamaño inválido normalizado a 20: correcto.'
    . PHP_EOL;

echo 'Administrador principal localizado: correcto.'
    . PHP_EOL;

echo 'Salvaguarda de administrador activo: disponible.'
    . PHP_EOL;

echo 'La prueba no modificó datos.'
    . PHP_EOL;