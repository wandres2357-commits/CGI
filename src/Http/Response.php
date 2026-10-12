<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class Response
{
    public static function redirect(
        string $path,
        int $status = 303
    ): never {
        if (!str_starts_with($path, '/')) {
            throw new RuntimeException(
                'La redirección debe utilizar una ruta interna.'
            );
        }

        if (
            str_starts_with($path, '//')
            || str_contains($path, "\r")
            || str_contains($path, "\n")
        ) {
            throw new RuntimeException(
                'La ruta de redirección no es válida.'
            );
        }

        header(
            'Location: ' . $path,
            true,
            $status
        );

        exit;
    }

    public static function status(
        int $status
    ): void {
        http_response_code($status);
    }

    private function __construct()
    {
    }
}