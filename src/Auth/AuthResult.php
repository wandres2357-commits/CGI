<?php

declare(strict_types=1);

namespace App\Auth;

final class AuthResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly string $code,
        public readonly string $message,
        public readonly ?array $user = null
    ) {
    }

    public static function success(
        array $user
    ): self {
        return new self(
            successful: true,
            code: 'AUTHENTICATED',
            message: 'Inicio de sesión correcto.',
            user: $user
        );
    }

    public static function failure(
        string $code = 'INVALID_CREDENTIALS'
    ): self {
        return new self(
            successful: false,
            code: $code,
            message: 'No fue posible iniciar sesión.',
            user: null
        );
    }

    public function requiresPasswordChange(): bool
    {
        return $this->successful
            && $this->user !== null
            && (bool) (
                $this->user['debe_cambiar_password']
                ?? false
            );
    }
}