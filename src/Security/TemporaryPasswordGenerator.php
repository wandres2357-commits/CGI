<?php

declare(strict_types=1);

namespace App\Security;

use InvalidArgumentException;
use RuntimeException;

final class TemporaryPasswordGenerator
{
    public const DEFAULT_LENGTH = 20;

    private const MINIMUM_LENGTH = 12;
    private const MAXIMUM_LENGTH = 128;

    private const UPPERCASE =
        'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LOWERCASE =
        'abcdefghijkmnpqrstuvwxyz';

    private const DIGITS =
        '23456789';

    private const SYMBOLS =
        '!@#$%&*+-_=?.';

    private const AMBIGUOUS_CHARACTERS =
        '0OoIl1';

    public function generate(
        int $length = self::DEFAULT_LENGTH
    ): string {
        $this->validateLength($length);

        $characterGroups = [
            self::UPPERCASE,
            self::LOWERCASE,
            self::DIGITS,
            self::SYMBOLS,
        ];

        $passwordCharacters = [];

        foreach ($characterGroups as $group) {
            $passwordCharacters[] =
                $this->randomCharacter($group);
        }

        $allCharacters = implode(
            '',
            $characterGroups
        );

        while (
            count($passwordCharacters)
            < $length
        ) {
            $passwordCharacters[] =
                $this->randomCharacter(
                    $allCharacters
                );
        }

        $this->secureShuffle(
            $passwordCharacters
        );

        $password = implode(
            '',
            $passwordCharacters
        );

        if (!$this->meetsPolicy($password)) {
            throw new RuntimeException(
                'La contraseña generada no cumple '
                . 'la política de seguridad.'
            );
        }

        return $password;
    }

    public function meetsPolicy(
        string $password
    ): bool {
        $length = strlen($password);

        if (
            $length < self::MINIMUM_LENGTH
            || $length > self::MAXIMUM_LENGTH
        ) {
            return false;
        }

        if (
            strpbrk(
                $password,
                self::AMBIGUOUS_CHARACTERS
            ) !== false
        ) {
            return false;
        }

        if (
            preg_match(
                '/[A-Z]/',
                $password
            ) !== 1
        ) {
            return false;
        }

        if (
            preg_match(
                '/[a-z]/',
                $password
            ) !== 1
        ) {
            return false;
        }

        if (
            preg_match(
                '/[0-9]/',
                $password
            ) !== 1
        ) {
            return false;
        }

        if (
            preg_match(
                '/[^a-zA-Z0-9]/',
                $password
            ) !== 1
        ) {
            return false;
        }

        return true;
    }

    public function containsAmbiguousCharacters(
        string $password
    ): bool {
        return strpbrk(
            $password,
            self::AMBIGUOUS_CHARACTERS
        ) !== false;
    }

    private function validateLength(
        int $length
    ): void {
        if ($length < self::MINIMUM_LENGTH) {
            throw new InvalidArgumentException(
                sprintf(
                    'La longitud mínima permitida es %d.',
                    self::MINIMUM_LENGTH
                )
            );
        }

        if ($length > self::MAXIMUM_LENGTH) {
            throw new InvalidArgumentException(
                sprintf(
                    'La longitud máxima permitida es %d.',
                    self::MAXIMUM_LENGTH
                )
            );
        }
    }

    private function randomCharacter(
        string $characters
    ): string {
        $maximumIndex =
            strlen($characters) - 1;

        if ($maximumIndex < 0) {
            throw new RuntimeException(
                'El conjunto de caracteres está vacío.'
            );
        }

        return $characters[
            random_int(
                0,
                $maximumIndex
            )
        ];
    }

    private function secureShuffle(
        array &$characters
    ): void {
        for (
            $index = count($characters) - 1;
            $index > 0;
            $index--
        ) {
            $randomIndex = random_int(
                0,
                $index
            );

            if ($randomIndex === $index) {
                continue;
            }

            $temporary =
                $characters[$index];

            $characters[$index] =
                $characters[$randomIndex];

            $characters[$randomIndex] =
                $temporary;
        }
    }
}