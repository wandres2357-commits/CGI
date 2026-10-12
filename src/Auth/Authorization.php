<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Response;

final class Authorization
{
    public static function requireGuest(): void
    {
        if (!SessionManager::isAuthenticated()) {
            return;
        }

        if (
            SessionManager::requiresPasswordChange()
        ) {
            Response::redirect(
                '/cambiar-password'
            );
        }

        Response::redirect('/panel');
    }

    public static function requireAuthenticated(): void
    {
        if (SessionManager::isAuthenticated()) {
            return;
        }

        SessionManager::flash(
            'warning',
            'Debe iniciar sesión para continuar.'
        );

        Response::redirect('/login');
    }

    public static function requirePasswordChanged(): void
    {
        self::requireAuthenticated();

        if (
            !SessionManager::requiresPasswordChange()
        ) {
            return;
        }

        SessionManager::flash(
            'warning',
            'Debe cambiar la contraseña temporal '
            . 'antes de continuar.'
        );

        Response::redirect(
            '/cambiar-password'
        );
    }

    public static function requireRole(
        string $role
    ): void {
        self::requirePasswordChanged();

        if (SessionManager::hasRole($role)) {
            return;
        }

        Response::status(403);

        echo 'Acceso denegado.';

        exit;
    }

    private function __construct()
    {
    }
}