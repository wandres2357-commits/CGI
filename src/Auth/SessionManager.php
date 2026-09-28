<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config\Env;
use RuntimeException;

final class SessionManager
{
    private const USER_KEY = 'authenticated_user';
    private const CREATED_AT_KEY = 'session_created_at';
    private const LAST_ACTIVITY_KEY = 'session_last_activity';
    private const AUTHENTICATED_AT_KEY = 'authenticated_at';
    private const FLASH_KEY = 'flash_messages';

    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;

            return;
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException(
                sprintf(
                    'No se puede iniciar la sesión porque ya se enviaron '
                    . 'encabezados en %s:%d.',
                    $file,
                    $line
                )
            );
        }

        $sessionPath = Env::get(
            'SESSION_STORAGE_PATH',
            BASE_PATH
                . DIRECTORY_SEPARATOR
                . 'storage'
                . DIRECTORY_SEPARATOR
                . 'sessions'
        );

        if (
            !is_string($sessionPath)
            || trim($sessionPath) === ''
        ) {
            throw new RuntimeException(
                'La ruta de almacenamiento de sesiones no es válida.'
            );
        }

        if (!is_dir($sessionPath)) {
            if (
                !mkdir(
                    $sessionPath,
                    0770,
                    true
                )
                && !is_dir($sessionPath)
            ) {
                throw new RuntimeException(
                    'No fue posible crear el directorio de sesiones.'
                );
            }
        }

        if (!is_writable($sessionPath)) {
            throw new RuntimeException(
                'El directorio de sesiones no tiene permisos de escritura.'
            );
        }

        session_name(
            Env::get(
                'SESSION_NAME',
                'inventario_ti_session'
            ) ?? 'inventario_ti_session'
        );

        session_save_path($sessionPath);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        if (!session_start()) {
            throw new RuntimeException(
                'No fue posible iniciar la sesión PHP.'
            );
        }

        self::$started = true;

        $now = time();

        if (!isset($_SESSION[self::CREATED_AT_KEY])) {
            $_SESSION[self::CREATED_AT_KEY] = $now;
        }

        if (!isset($_SESSION[self::LAST_ACTIVITY_KEY])) {
            $_SESSION[self::LAST_ACTIVITY_KEY] = $now;
        }
    }

    public static function enforceLifetime(): void
    {
        self::start();

        $now = time();

        $idleMinutes = max(
            1,
            Env::int(
                'SESSION_IDLE_MINUTES',
                30
            )
        );

        $maximumHours = max(
            1,
            Env::int(
                'SESSION_MAX_HOURS',
                8
            )
        );

        $idleSeconds = $idleMinutes * 60;
        $maximumSeconds = $maximumHours * 3600;

        $createdAt = (int) (
            $_SESSION[self::CREATED_AT_KEY]
            ?? $now
        );

        $lastActivity = (int) (
            $_SESSION[self::LAST_ACTIVITY_KEY]
            ?? $now
        );

        if (($now - $lastActivity) > $idleSeconds) {
            self::destroy();

            throw new SessionExpiredException(
                'La sesión expiró por inactividad.'
            );
        }

        if (($now - $createdAt) > $maximumSeconds) {
            self::destroy();

            throw new SessionExpiredException(
                'La sesión alcanzó su duración máxima.'
            );
        }

        $_SESSION[self::LAST_ACTIVITY_KEY] = $now;
    }

    public static function regenerate(): void
    {
        self::start();

        if (!session_regenerate_id(true)) {
            throw new RuntimeException(
                'No fue posible regenerar el identificador de sesión.'
            );
        }

        $now = time();

        $_SESSION[self::CREATED_AT_KEY] = $now;
        $_SESSION[self::LAST_ACTIVITY_KEY] = $now;
    }

    public static function authenticate(array $user): void
    {
        self::start();
        self::regenerate();

        $requiredKeys = [
            'id',
            'nombre_usuario',
            'nombre_completo',
            'correo',
            'rol',
            'debe_cambiar_password',
        ];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $user)) {
                throw new RuntimeException(
                    sprintf(
                        'Falta el dato de sesión obligatorio: %s',
                        $key
                    )
                );
            }
        }

        $_SESSION[self::USER_KEY] = [
            'id' => (int) $user['id'],
            'nombre_usuario' => (string) $user['nombre_usuario'],
            'nombre_completo' => (string) $user['nombre_completo'],
            'correo' => (string) $user['correo'],
            'rol' => (string) $user['rol'],
            'debe_cambiar_password' =>
                (bool) $user['debe_cambiar_password'],
        ];

        $_SESSION[self::AUTHENTICATED_AT_KEY] = time();
    }

    public static function isAuthenticated(): bool
    {
        self::start();

        return isset(
            $_SESSION[self::USER_KEY]['id']
        );
    }

    public static function user(): ?array
    {
        self::start();

        $user = $_SESSION[self::USER_KEY] ?? null;

        return is_array($user)
            ? $user
            : null;
    }

    public static function userId(): ?int
    {
        $user = self::user();

        if ($user === null) {
            return null;
        }

        return (int) $user['id'];
    }

    public static function role(): ?string
    {
        $user = self::user();

        if ($user === null) {
            return null;
        }

        return (string) $user['rol'];
    }

    public static function requiresPasswordChange(): bool
    {
        $user = self::user();

        if ($user === null) {
            return false;
        }

        return (bool) $user['debe_cambiar_password'];
    }

    public static function markPasswordChanged(): void
    {
        self::start();

        if (
            isset(
                $_SESSION[self::USER_KEY][
                    'debe_cambiar_password'
                ]
            )
        ) {
            $_SESSION[self::USER_KEY][
                'debe_cambiar_password'
            ] = false;
        }
    }

    public static function hasRole(string $role): bool
    {
        $currentRole = self::role();

        return $currentRole !== null
            && hash_equals(
                $currentRole,
                $role
            );
    }

    public static function flash(
        string $type,
        string $message
    ): void {
        self::start();

        $_SESSION[self::FLASH_KEY][] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    public static function consumeFlash(): array
    {
        self::start();

        $messages = $_SESSION[self::FLASH_KEY] ?? [];

        unset($_SESSION[self::FLASH_KEY]);

        return is_array($messages)
            ? $messages
            : [];
    }

    public static function id(): string
    {
        self::start();

        return session_id();
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::$started = false;

            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $parameters['path'],
                    'domain' => $parameters['domain'],
                    'secure' => $parameters['secure'],
                    'httponly' => $parameters['httponly'],
                    'samesite' =>
                        $parameters['samesite']
                        ?? 'Lax',
                ]
            );
        }

        session_destroy();

        self::$started = false;
    }

    private function __construct()
    {
    }
}