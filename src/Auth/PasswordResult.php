<?php

declare(strict_types=1);

namespace App\Auth;

final class PasswordResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly string $code,
        public readonly string $message,
        public readonly array $errors = []
    ) {
    }

    public static function success(): self
    {
        return new self(
            successful: true,
            code: 'PASSWORD_CHANGED',
            message: 'La contraseña se cambió correctamente.'
        );
    }

    public static function failure(
        string $code,
        string $message,
        array $errors = []
    ): self {
        return new self(
            successful: false,
            code: $code,
            message: $message,
            errors: $errors
        );
    }
}