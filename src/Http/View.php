<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class View
{
    public static function render(
        string $view,
        array $data = [],
        int $status = 200
    ): void {
        if (
            preg_match(
                '/^[a-z0-9\/_-]+$/',
                $view
            ) !== 1
        ) {
            throw new RuntimeException(
                'El nombre de la vista no es válido.'
            );
        }

        $viewFile = BASE_PATH
            . DIRECTORY_SEPARATOR
            . 'resources'
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . $view
            . '.php';

        $layoutFile = BASE_PATH
            . DIRECTORY_SEPARATOR
            . 'resources'
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . 'layout.php';

        if (
            !is_file($viewFile)
            || !is_file($layoutFile)
        ) {
            throw new RuntimeException(
                'No fue posible localizar la vista solicitada.'
            );
        }

        http_response_code($status);

        extract(
            $data,
            EXTR_SKIP
        );

        ob_start();

        require $viewFile;

        $content = ob_get_clean();

        if (!is_string($content)) {
            throw new RuntimeException(
                'No fue posible generar la vista.'
            );
        }

        require $layoutFile;
    }

    public static function escape(
        mixed $value
    ): string {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES
            | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    private function __construct()
    {
    }
}