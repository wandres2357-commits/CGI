<?php

declare(strict_types=1);

use App\Security\TemporaryPasswordGenerator;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'bootstrap'
    . DIRECTORY_SEPARATOR
    . 'bootstrap.php';

$generator = new TemporaryPasswordGenerator();

$iterations = 1000;
$generatedPasswords = [];

for (
    $iteration = 1;
    $iteration <= $iterations;
    $iteration++
) {
    $password = $generator->generate();

    if (
        strlen($password)
        !== TemporaryPasswordGenerator::DEFAULT_LENGTH
    ) {
        fwrite(
            STDERR,
            'ERROR: longitud incorrecta.'
            . PHP_EOL
        );

        exit(1);
    }

    if (!$generator->meetsPolicy($password)) {
        fwrite(
            STDERR,
            'ERROR: una contraseña generada '
            . 'no cumple la política.'
            . PHP_EOL
        );

        exit(1);
    }

    if (
        $generator->containsAmbiguousCharacters(
            $password
        )
    ) {
        fwrite(
            STDERR,
            'ERROR: se generó un carácter ambiguo.'
            . PHP_EOL
        );

        exit(1);
    }

    if (isset($generatedPasswords[$password])) {
        fwrite(
            STDERR,
            'ERROR: se generó una contraseña duplicada '
            . 'durante la prueba.'
            . PHP_EOL
        );

        exit(1);
    }

    $generatedPasswords[$password] = true;

    if (function_exists('sodium_memzero')) {
        sodium_memzero($password);
    }

    $password = '';
}

try {
    $generator->generate(11);

    fwrite(
        STDERR,
        'ERROR: se aceptó una longitud inferior '
        . 'a la permitida.'
        . PHP_EOL
    );

    exit(1);
} catch (InvalidArgumentException) {
}

try {
    $generator->generate(129);

    fwrite(
        STDERR,
        'ERROR: se aceptó una longitud superior '
        . 'a la permitida.'
        . PHP_EOL
    );

    exit(1);
} catch (InvalidArgumentException) {
}

echo 'Cantidad de pruebas: '
    . $iterations
    . PHP_EOL;

echo 'Longitud de 20 caracteres: correcta.'
    . PHP_EOL;

echo 'Mayúscula obligatoria: correcta.'
    . PHP_EOL;

echo 'Minúscula obligatoria: correcta.'
    . PHP_EOL;

echo 'Número obligatorio: correcto.'
    . PHP_EOL;

echo 'Símbolo obligatorio: correcto.'
    . PHP_EOL;

echo 'Caracteres ambiguos excluidos: correcto.'
    . PHP_EOL;

echo 'Duplicados durante la prueba: ninguno.'
    . PHP_EOL;

echo 'Límite mínimo validado: correcto.'
    . PHP_EOL;

echo 'Límite máximo validado: correcto.'
    . PHP_EOL;