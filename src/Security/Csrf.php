<?php

declare(strict_types=1);

namespace App\Security;

use App\Auth\SessionManager;
use RuntimeException;

final class Csrf
{
    private const SESSION_KEY = 'csrf_tokens';
    private const TOKEN_BYTES = 32;
    private const MAX_TOKENS = 10;

    public static function generate(
        string $form
    ): string {
        SessionManager::start();

        self::validateFormName($form);

        $token = bin2hex(
            random_bytes(
                self::TOKEN_BYTES
            )
        );

        if (
            !isset($_SESSION[self::SESSION_KEY])
            || !is_array(
                $_SESSION[self::SESSION_KEY]
            )
        ) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        if (
            !isset(
                $_SESSION[self::SESSION_KEY][$form]
            )
            || !is_array(
                $_SESSION[self::SESSION_KEY][$form]
            )
        ) {
            $_SESSION[self::SESSION_KEY][$form] = [];
        }

        $_SESSION[self::SESSION_KEY][$form][] = [
            'hash' => hash(
                'sha256',
                $token
            ),
            'created_at' => time(),
        ];

        $_SESSION[self::SESSION_KEY][$form] =
            array_slice(
                $_SESSION[self::SESSION_KEY][$form],
                -self::MAX_TOKENS
            );

        return $token;
    }

    public static function validate(
        string $form,
        ?string $token
    ): bool {
        SessionManager::start();

        self::validateFormName($form);

        if (
            $token === null
            || $token === ''
        ) {
            return false;
        }

        $tokens =
            $_SESSION[self::SESSION_KEY][$form]
            ?? [];

        if (!is_array($tokens)) {
            return false;
        }

        $submittedHash = hash(
            'sha256',
            $token
        );

        $matchedIndex = null;

        foreach ($tokens as $index => $storedToken) {
            if (
                !is_array($storedToken)
                || !isset($storedToken['hash'])
            ) {
                continue;
            }

            if (
                hash_equals(
                    (string) $storedToken['hash'],
                    $submittedHash
                )
            ) {
                $matchedIndex = $index;
                break;
            }
        }

        if ($matchedIndex === null) {
            return false;
        }

        unset(
            $_SESSION[self::SESSION_KEY][$form][
                $matchedIndex
            ]
        );

        $_SESSION[self::SESSION_KEY][$form] =
            array_values(
                $_SESSION[self::SESSION_KEY][$form]
            );

        return true;
    }

    public static function rotate(
        string $form
    ): string {
        self::clear($form);

        return self::generate($form);
    }

    public static function clear(
        string $form
    ): void {
        SessionManager::start();

        self::validateFormName($form);

        unset(
            $_SESSION[self::SESSION_KEY][$form]
        );
    }

    public static function clearAll(): void
    {
        SessionManager::start();

        unset(
            $_SESSION[self::SESSION_KEY]
        );
    }

    private static function validateFormName(
        string $form
    ): void {
        if (
            preg_match(
                '/^[a-z0-9._-]{1,80}$/',
                $form
            ) !== 1
        ) {
            throw new RuntimeException(
                'El identificador del formulario CSRF no es válido.'
            );
        }
    }

    private function __construct()
    {
    }
}