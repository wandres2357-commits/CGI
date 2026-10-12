<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Env;
use PDO;
use PDOException;
use RuntimeException;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            Env::require('DB_HOST'),
            Env::int('DB_PORT', 3306),
            Env::require('DB_DATABASE'),
            Env::get('DB_CHARSET', 'utf8mb4')
        );

        try {
            self::$pdo = new PDO(
                $dsn,
                Env::require('DB_USERNAME'),
                Env::require('DB_PASSWORD'),
                [
                    PDO::ATTR_ERRMODE =>
                        PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE =>
                        PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES =>
                        false,
                    PDO::ATTR_PERSISTENT =>
                        false,
                ]
            );

            self::configureConnection(
                self::$pdo
            );

            return self::$pdo;
        } catch (PDOException $exception) {
            self::$pdo = null;

            throw new RuntimeException(
                'No fue posible conectar con la base de datos.',
                0,
                $exception
            );
        } catch (\Throwable $exception) {
            self::$pdo = null;

            throw new RuntimeException(
                'No fue posible configurar la conexión '
                . 'con la base de datos.',
                0,
                $exception
            );
        }
    }

    private static function configureConnection(
        PDO $pdo
    ): void {
        $pdo->exec(
            "SET time_zone = '-05:00'"
        );

        $pdo->exec(
            "SET NAMES utf8mb4 "
            . "COLLATE utf8mb4_unicode_ci"
        );
    }

    private function __construct()
    {
    }
}