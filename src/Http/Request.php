<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    public static function method(): string
    {
        return strtoupper(
            (string) (
                $_SERVER['REQUEST_METHOD']
                ?? 'GET'
            )
        );
    }

    public static function path(): string
    {
        $uri = (string) (
            $_SERVER['REQUEST_URI']
            ?? '/'
        );

        $path = parse_url(
            $uri,
            PHP_URL_PATH
        );

        if (!is_string($path) || $path === '') {
            return '/';
        }

        $path = rawurldecode($path);

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === ''
            ? '/'
            : $path;
    }

    public static function postString(
        string $key,
        string $default = ''
    ): string {
        $value = $_POST[$key] ?? $default;

        if (!is_string($value)) {
            return $default;
        }

        return trim($value);
    }

    public static function postRawString(
        string $key,
        string $default = ''
    ): string {
        $value = $_POST[$key] ?? $default;

        return is_string($value)
            ? $value
            : $default;
    }

    public static function ipAddress(): ?string
    {
        $value = $_SERVER['REMOTE_ADDR'] ?? null;

        if (!is_string($value) || $value === '') {
            return null;
        }

        return mb_substr(
            $value,
            0,
            45
        );
    }

    public static function userAgent(): ?string
    {
        $value = $_SERVER[
            'HTTP_USER_AGENT'
        ] ?? null;

        if (!is_string($value) || $value === '') {
            return null;
        }

        return mb_substr(
            $value,
            0,
            500
        );
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function queryString(
        string $key,
        string $default = ''
    ): string {
        $value = $_GET[$key] ?? $default;

        if (!is_string($value)) {
            return $default;
        }

        return trim($value);
    }

    public static function queryInt(
        string $key,
        int $default = 0
    ): int {
        $value = $_GET[$key] ?? null;

        if (
            is_int($value)
            || (
                is_string($value)
                && preg_match(
                    '/^-?[0-9]+$/',
                    $value
                ) === 1
            )
        ) {
            return (int) $value;
        }

        return $default;
    }

    private function __construct()
    {
    }
}