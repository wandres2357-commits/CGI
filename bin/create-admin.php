<?php

declare(strict_types=1);

use App\Database\Connection;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'bootstrap'
    . DIRECTORY_SEPARATOR
    . 'bootstrap.php';

/**
 * Lee una línea desde la consola.
 */
function readInput(string $message): string
{
    fwrite(STDOUT, $message);

    $value = fgets(STDIN);

    if ($value === false) {
        throw new RuntimeException(
            'No fue posible leer la entrada de la terminal.'
        );
    }

    return trim($value);
}

/**
 * Lee una contraseña sin mostrarla en una terminal Unix.
 *
 * El comando debe ejecutarse mediante SSH en alwaysdata.
 */
function readHiddenPassword(string $message): string
{
    if (PHP_OS_FAMILY === 'Windows') {
        throw new RuntimeException(
            'La captura oculta de contraseña debe ejecutarse '
            . 'desde la terminal SSH de alwaysdata.'
        );
    }

    fwrite(STDOUT, $message);

    $terminalState = shell_exec('stty -g');

    if (!is_string($terminalState) || trim($terminalState) === '') {
        throw new RuntimeException(
            'No fue posible obtener el estado de la terminal.'
        );
    }

    $terminalState = trim($terminalState);

    try {
        shell_exec('stty -echo');

        $password = fgets(STDIN);

        fwrite(STDOUT, PHP_EOL);

        if ($password === false) {
            throw new RuntimeException(
                'No fue posible leer la contraseña.'
            );
        }

        return rtrim($password, "\r\n");
    } finally {
        shell_exec(
            'stty ' . escapeshellarg($terminalState)
        );
    }
}

/**
 * Valida el nombre de usuario.
 */
function validateUsername(string $username): array
{
    $errors = [];

    if ($username === '') {
        $errors[] = 'El nombre de usuario es obligatorio.';
    }

    if (
        strlen($username) < 4
        || strlen($username) > 100
    ) {
        $errors[] = 'El usuario debe tener entre 4 y 100 caracteres.';
    }

    if (
        $username !== ''
        && preg_match(
            '/^[a-z0-9._-]+$/',
            $username
        ) !== 1
    ) {
        $errors[] =
            'El usuario solo puede contener letras minúsculas, '
            . 'números, punto, guion y guion bajo.';
    }

    return $errors;
}

/**
 * Valida la contraseña inicial.
 */
function validatePassword(string $password): array
{
    $errors = [];

    if (strlen($password) < 12) {
        $errors[] =
            'La contraseña debe tener al menos 12 caracteres.';
    }

    if (strlen($password) > 128) {
        $errors[] =
            'La contraseña no puede superar 128 caracteres.';
    }

    if (preg_match('/[a-z]/', $password) !== 1) {
        $errors[] =
            'La contraseña debe incluir una letra minúscula.';
    }

    if (preg_match('/[A-Z]/', $password) !== 1) {
        $errors[] =
            'La contraseña debe incluir una letra mayúscula.';
    }

    if (preg_match('/[0-9]/', $password) !== 1) {
        $errors[] =
            'La contraseña debe incluir un número.';
    }

    if (
        preg_match(
            '/[^a-zA-Z0-9]/',
            $password
        ) !== 1
    ) {
        $errors[] =
            'La contraseña debe incluir un carácter especial.';
    }

    return $errors;
}

/**
 * Imprime errores de validación.
 */
function printErrors(array $errors): void
{
    foreach ($errors as $error) {
        fwrite(
            STDERR,
            sprintf(
                "- %s%s",
                $error,
                PHP_EOL
            )
        );
    }
}

fwrite(
    STDOUT,
    PHP_EOL
    . '========================================'
    . PHP_EOL
    . ' Creación del primer administrador'
    . PHP_EOL
    . '========================================'
    . PHP_EOL
);

try {
    $pdo = Connection::get();

    $roleStatement = $pdo->prepare(
        'SELECT id
         FROM roles
         WHERE codigo = :codigo
           AND activo = 1
         LIMIT 1'
    );

    $roleStatement->execute([
        'codigo' => 'ADMINISTRADOR',
    ]);

    $roleId = $roleStatement->fetchColumn();

    if ($roleId === false) {
        throw new RuntimeException(
            'No existe un rol ADMINISTRADOR activo.'
        );
    }

    $adminCountStatement = $pdo->query(
        'SELECT COUNT(*)
         FROM usuarios u
         INNER JOIN roles r
             ON r.id = u.rol_id
         WHERE r.codigo = \'ADMINISTRADOR\''
    );

    $existingAdminCount = (int) $adminCountStatement->fetchColumn();

    if ($existingAdminCount > 0) {
        fwrite(
            STDOUT,
            sprintf(
                'Advertencia: ya existen %d administradores.%s',
                $existingAdminCount,
                PHP_EOL
            )
        );

        $continue = strtolower(
            readInput(
                '¿Desea crear otro administrador? [s/N\]: '
            )
        );

        if (!in_array($continue, ['s', 'si', 'sí'], true)) {
            fwrite(
                STDOUT,
                'Operación cancelada.'
                . PHP_EOL
            );

            exit(0);
        }
    }

    do {
        $username = strtolower(
            readInput('Nombre de usuario: ')
        );

        $usernameErrors = validateUsername($username);

        if ($usernameErrors !== []) {
            printErrors($usernameErrors);
        }
    } while ($usernameErrors !== []);

    do {
        $fullName = readInput('Nombre completo: ');

        if (
            mb_strlen($fullName) < 3
            || mb_strlen($fullName) > 200
        ) {
            fwrite(
                STDERR,
                '- El nombre debe tener entre 3 y 200 caracteres.'
                . PHP_EOL
            );

            $fullNameIsValid = false;
        } else {
            $fullNameIsValid = true;
        }
    } while (!$fullNameIsValid);

    do {
        $email = strtolower(
            readInput('Correo institucional: ')
        );

        $emailIsValid =
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) !== false;

        if (!$emailIsValid || strlen($email) > 254) {
            fwrite(
                STDERR,
                '- Ingrese un correo electrónico válido.'
                . PHP_EOL
            );

            $emailIsValid = false;
        }
    } while (!$emailIsValid);

    do {
        $password = readHiddenPassword(
            'Contraseña inicial: '
        );

        $passwordErrors = validatePassword($password);

        if ($passwordErrors !== []) {
            printErrors($passwordErrors);
            continue;
        }

        $confirmation = readHiddenPassword(
            'Confirme la contraseña: '
        );

        if (!hash_equals($password, $confirmation)) {
            fwrite(
                STDERR,
                '- Las contraseñas no coinciden.'
                . PHP_EOL
            );

            $passwordErrors = [
                'Las contraseñas no coinciden.',
            ];
        }
    } while ($passwordErrors !== []);

    $duplicateStatement = $pdo->prepare(
        'SELECT nombre_usuario, correo
         FROM usuarios
         WHERE nombre_usuario = :nombre_usuario
            OR correo = :correo
         LIMIT 1'
    );

    $duplicateStatement->execute([
        'nombre_usuario' => $username,
        'correo' => $email,
    ]);

    $duplicateUser = $duplicateStatement->fetch();

    if (is_array($duplicateUser)) {
        throw new RuntimeException(
            'Ya existe un usuario con ese nombre o correo.'
        );
    }

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    if ($passwordHash === false) {
        throw new RuntimeException(
            'No fue posible generar el hash de contraseña.'
        );
    }

    $pdo->beginTransaction();

    try {
        $insertStatement = $pdo->prepare(
            'INSERT INTO usuarios (
                rol_id,
                nombre_usuario,
                nombre_completo,
                correo,
                password_hash,
                activo,
                debe_cambiar_password,
                intentos_fallidos,
                creado_en,
                actualizado_en
            ) VALUES (
                :rol_id,
                :nombre_usuario,
                :nombre_completo,
                :correo,
                :password_hash,
                1,
                1,
                0,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )'
        );

        $insertStatement->execute([
            'rol_id' => (int) $roleId,
            'nombre_usuario' => $username,
            'nombre_completo' => $fullName,
            'correo' => $email,
            'password_hash' => $passwordHash,
        ]);

        $userId = (int) $pdo->lastInsertId();

        $auditValues = json_encode(
            [
                'id' => $userId,
                'nombre_usuario' => $username,
                'nombre_completo' => $fullName,
                'correo' => $email,
                'rol' => 'ADMINISTRADOR',
                'activo' => true,
                'debe_cambiar_password' => true,
            ],
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        $auditStatement = $pdo->prepare(
            'INSERT INTO auditoria (
                usuario_id,
                fecha_evento,
                direccion_ip,
                agente_usuario,
                modulo,
                accion,
                entidad_tipo,
                entidad_id,
                valores_anteriores,
                valores_nuevos,
                resultado,
                motivo,
                id_correlacion
            ) VALUES (
                :usuario_id,
                CURRENT_TIMESTAMP(6),
                NULL,
                :agente_usuario,
                :modulo,
                :accion,
                :entidad_tipo,
                :entidad_id,
                NULL,
                :valores_nuevos,
                :resultado,
                :motivo,
                NULL
            )'
        );

         $auditStatement->execute([
            'usuario_id' => $userId,
            'agente_usuario' => 'CLI bin/create-admin.php',
            'modulo' => 'USUARIOS',
            'accion' => 'CREAR_ADMINISTRADOR_INICIAL',
            'entidad_tipo' => 'USUARIO',
            'entidad_id' => $userId,
            'valores_nuevos' => $auditValues,
            'resultado' => 'EXITOSO',
            'motivo' => 'Creación segura desde consola',
        ]);

        $pdo->commit();

        if (function_exists('sodium_memzero')) {
            sodium_memzero($password);
            sodium_memzero($confirmation);
        }

        $password = '';
        $confirmation = '';
        $passwordHash = '';

        fwrite(
            STDOUT,
            PHP_EOL
            . 'Administrador creado correctamente.'
            . PHP_EOL
            . sprintf(
                'ID: %d%s',
                $userId,
                PHP_EOL
            )
            . sprintf(
                'Usuario: %s%s',
                $username,
                PHP_EOL
            )
            . sprintf(
                'Correo: %s%s',
                $email,
                PHP_EOL
            )
            . 'Cambio de contraseña requerido: sí'
            . PHP_EOL
        );
    } catch (\Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
} catch (\Throwable $exception) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'No fue posible crear el administrador.'
        . PHP_EOL
        . 'Detalle: '
        . $exception->getMessage()
        . PHP_EOL
    );

    exit(1);
}