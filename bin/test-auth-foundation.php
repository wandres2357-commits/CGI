<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\UserRepository;
use App\Services\AuditService;

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

$userRepository = new UserRepository(
    $pdo
);

$auditService = new AuditService(
    $pdo
);

$userByUsername = $userRepository
    ->findByIdentifier('wlugo');

if ($userByUsername === null) {
    fwrite(
        STDERR,
        'ERROR: no se encontró el administrador por usuario.'
        . PHP_EOL
    );

    exit(1);
}

$userByEmail = $userRepository
    ->findByIdentifier(
        $userByUsername['correo']
    );

if ($userByEmail === null) {
    fwrite(
        STDERR,
        'ERROR: no se encontró el administrador por correo.'
        . PHP_EOL
    );

    exit(1);
}

if (
    $userByUsername['id']
    !== $userByEmail['id']
) {
    fwrite(
        STDERR,
        'ERROR: usuario y correo resolvieron cuentas distintas.'
        . PHP_EOL
    );

    exit(1);
}

if (
    $userByUsername['rol']
    !== 'ADMINISTRADOR'
) {
    fwrite(
        STDERR,
        'ERROR: el usuario no tiene rol ADMINISTRADOR.'
        . PHP_EOL
    );

    exit(1);
}

if (!$userByUsername['activo']) {
    fwrite(
        STDERR,
        'ERROR: el administrador está inactivo.'
        . PHP_EOL
    );

    exit(1);
}

$unknownUser = $userRepository
    ->findByIdentifier(
        'usuario.inexistente'
    );

if ($unknownUser !== null) {
    fwrite(
        STDERR,
        'ERROR: se encontró un usuario inexistente.'
        . PHP_EOL
    );

    exit(1);
}

$pdo->beginTransaction();

try {
    $auditService->recordAuthentication(
        userId: $userByUsername['id'],
        action: 'PRUEBA_BASE_AUTENTICACION',
        result: 'EXITOSO',
        reason: 'Prueba transaccional, será revertida',
        identifier: 'wlugo',
        ipAddress: '127.0.0.1',
        userAgent: 'CLI test-auth-foundation.php'
    );

    $countStatement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM auditoria
         WHERE usuario_id = :usuario_id
           AND accion =
               :accion'
    );

    $countStatement->execute([
        'usuario_id' =>
            $userByUsername['id'],
        'accion' =>
            'PRUEBA_BASE_AUTENTICACION',
    ]);

    $auditCount = (int) $countStatement
        ->fetchColumn();

    if ($auditCount < 1) {
        throw new RuntimeException(
            'No se generó el registro temporal de auditoría.'
        );
    }

    $pdo->rollBack();
} catch (\Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(
        STDERR,
        'ERROR: '
        . $exception->getMessage()
        . PHP_EOL
    );

    exit(1);
}

echo 'Búsqueda por usuario: correcta.'
    . PHP_EOL;

echo 'Búsqueda por correo: correcta.'
    . PHP_EOL;

echo 'Normalización del usuario: correcta.'
    . PHP_EOL;

echo 'Usuario inexistente rechazado: correcto.'
    . PHP_EOL;

echo 'Rol ADMINISTRADOR validado: correcto.'
    . PHP_EOL;

echo 'Estado activo validado: correcto.'
    . PHP_EOL;

echo 'Registro de auditoría transaccional: correcto.'
    . PHP_EOL;

echo 'Rollback de prueba aplicado: correcto.'
    . PHP_EOL;