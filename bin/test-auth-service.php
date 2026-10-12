<?php

declare(strict_types=1);

use App\Auth\AuthService;
use App\Auth\SessionManager;
use App\Database\Connection;
use App\Repositories\SessionRepository;
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

function readTestInput(
    string $message
): string {
    fwrite(STDOUT, $message);

    $value = fgets(STDIN);

    if ($value === false) {
        throw new RuntimeException(
            'No fue posible leer la entrada.'
        );
    }

    return trim($value);
}

function readTestPassword(
    string $message
): string {
    fwrite(STDOUT, $message);

    $terminalState = trim(
        (string) shell_exec('stty -g')
    );

    if ($terminalState === '') {
        throw new RuntimeException(
            'No fue posible obtener '
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
            . escapeshellarg($terminalState)
        );
    }
}

$pdo = Connection::get();

$users = new UserRepository($pdo);
$sessions = new SessionRepository($pdo);
$audit = new AuditService($pdo);

$auth = new AuthService(
    pdo: $pdo,
    users: $users,
    sessions: $sessions,
    audit: $audit
);

$identifier = readTestInput(
    'Usuario o correo institucional: '
);

$password = readTestPassword(
    'Contraseña: '
);

try {
    $result = $auth->attempt(
        identifier: $identifier,
        password: $password,
        ipAddress: '127.0.0.1',
        userAgent: 'CLI test-auth-service.php'
    );

    if (
        $password !== ''
        && function_exists('sodium_memzero')
    ) {
        sodium_memzero($password);
    }

    $password = '';

    if (!$result->successful) {
        fwrite(
            STDERR,
            'Autenticación rechazada.'
            . PHP_EOL
        );

        exit(1);
    }

    $sessionUser = SessionManager::user();

    if ($sessionUser === null) {
        throw new RuntimeException(
            'No se creó la sesión autenticada.'
        );
    }

    echo 'Autenticación correcta.'
        . PHP_EOL;

    echo 'Usuario: '
        . $sessionUser['nombre_usuario']
        . PHP_EOL;

    echo 'Rol: '
        . $sessionUser['rol']
        . PHP_EOL;

    echo 'Cambio de contraseña requerido: '
        . (
            $result->requiresPasswordChange()
                ? 'sí'
                : 'no'
        )
        . PHP_EOL;

    $sessionIsActive = $sessions->isActive(
        SessionManager::id(),
        (int) $sessionUser['id']
    );

    echo 'Sesión registrada: '
        . ($sessionIsActive ? 'sí' : 'no')
        . PHP_EOL;

    if (!$sessionIsActive) {
        throw new RuntimeException(
            'La sesión no quedó activa en la base.'
        );
    }

    $auth->logout(
        reason: 'FIN_PRUEBA_AUTENTICACION',
        ipAddress: '127.0.0.1',
        userAgent: 'CLI test-auth-service.php'
    );

    echo 'Cierre de sesión: correcto.'
        . PHP_EOL;
} catch (\Throwable $exception) {
    if (
        isset($password)
        && $password !== ''
        && function_exists('sodium_memzero')
    ) {
        sodium_memzero($password);
    }

    SessionManager::destroy();

    fwrite(
        STDERR,
        'ERROR: '
        . $exception->getMessage()
        . PHP_EOL
    );

    exit(1);
}