<?php

declare(strict_types=1);

use App\Auth\Authorization;
use App\Auth\AuthService;
use App\Auth\SessionExpiredException;
use App\Auth\SessionManager;
use App\Database\Connection;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Security\Csrf;
use App\Services\AuditService;
use App\Auth\PasswordService;
use App\Http\Controllers\UserAdministrationController;
use App\Http\Controllers\SiteLocationController;
use App\Repositories\SiteLocationRepository;
use App\Services\SiteLocationService;
use App\Repositories\UserAdministrationRepository;
use App\Security\TemporaryPasswordGenerator;
use App\Services\UserAdministrationService;

require dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'bootstrap'
    . DIRECTORY_SEPARATOR
    . 'bootstrap.php';

header(
    'Content-Type: text/html; charset=UTF-8'
);

header(
    'X-Content-Type-Options: nosniff'
);

header(
    'X-Frame-Options: DENY'
);

header(
    'Referrer-Policy: same-origin'
);

header(
    "Content-Security-Policy: "
    . "default-src 'self'; "
    . "style-src 'self'; "
    . "img-src 'self' data:; "
    . "form-action 'self'; "
    . "frame-ancestors 'none'; "
    . "base-uri 'self'"
);

$pdo = Connection::get();

$users = new UserRepository($pdo);
$sessions = new SessionRepository($pdo);
$audit = new AuditService($pdo);

$auth = new AuthService(
    pdo: $pdo,
    users: $users,
    sessions: $sessions,
    audit: $audit
);

$passwordService = new PasswordService(
    pdo: $pdo,
    users: $users,
    sessions: $sessions,
    audit: $audit
);

$userAdministrationRepository =
    new UserAdministrationRepository(
        $pdo
    );

$userAdministrationService =
    new UserAdministrationService(
        pdo: $pdo,
        repository:
            $userAdministrationRepository,
        audit: $audit,
        passwordGenerator:
            new TemporaryPasswordGenerator()
    );

$userAdministrationController =
    new UserAdministrationController(
        $userAdministrationService
    );

$siteLocationRepository =
    new SiteLocationRepository(
        $pdo
    );

$siteLocationService =
    new SiteLocationService(
        $siteLocationRepository
    );

$siteLocationController =
    new SiteLocationController(
        $siteLocationService
    );

SessionManager::start();

try {
    SessionManager::enforceLifetime();
} catch (SessionExpiredException) {
    SessionManager::flash(
        'warning',
        'La sesión expiró. Inicie sesión nuevamente.'
    );

    Response::redirect('/login');
}

if (
    SessionManager::isAuthenticated()
    && !$auth->sessionIsValid()
) {
    SessionManager::destroy();
    SessionManager::start();

    SessionManager::flash(
        'warning',
        'La sesión ya no está activa. '
        . 'Inicie sesión nuevamente.'
    );

    Response::redirect('/login');
}

if (SessionManager::isAuthenticated()) {
    $auth->touchAuthenticatedSession();
}

$method = Request::method();
$path = Request::path();

try {
    if (
        $userAdministrationController
            ->handle(
                $method,
                $path
            )
    ) {
        exit;
    }

    if (
        $siteLocationController
            ->handle(
                $method,
                $path
            )
    ) {
        exit;
    }

    if ($method === 'GET' && $path === '/') {

        if (!SessionManager::isAuthenticated()) {
            Response::redirect('/login');
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

    if ($method === 'GET' && $path === '/login') {
        Authorization::requireGuest();

        View::render(
            'auth/login',
            [
                'title' => 'Iniciar sesión',
                'identifier' => '',
            ]
        );

        exit;
    }

    if ($method === 'POST' && $path === '/login') {
        Authorization::requireGuest();

        $identifier = strtolower(
            Request::postString('identifier')
        );

        $password = Request::postRawString(
            'password'
        );

        $csrfIsValid = Csrf::validate(
            'login',
            Request::postRawString('_token')
        );

        if (!$csrfIsValid) {
            SessionManager::flash(
                'error',
                'La solicitud no es válida. '
                . 'Intente nuevamente.'
            );

            Response::redirect('/login');
        }

        $result = $auth->attempt(
            identifier: $identifier,
            password: $password,
            ipAddress: Request::ipAddress(),
            userAgent: Request::userAgent()
        );

        if (!$result->successful) {
            SessionManager::flash(
                'error',
                $result->message
            );

            View::render(
                'auth/login',
                [
                    'title' => 'Iniciar sesión',
                    'identifier' => $identifier,
                ],
                422
            );

            exit;
        }

        Csrf::clearAll();

        if ($result->requiresPasswordChange()) {
            Response::redirect(
                '/cambiar-password'
            );
        }

        Response::redirect('/panel');
    }

    if (
        $method === 'GET'
        && $path === '/cambiar-password'
    ) {
        Authorization::requireAuthenticated();

        View::render(
            'auth/change-password',
            [
                'title' => 'Cambiar contraseña',
            ]
        );

        exit;
    }

        if (
        $method === 'POST'
        && $path === '/cambiar-password'
    ) {
        Authorization::requireAuthenticated();

        if (
            !Csrf::validate(
                'change_password',
                Request::postRawString('_token')
            )
        ) {
            SessionManager::flash(
                'error',
                'La solicitud no es válida.'
            );

            Response::redirect(
                '/cambiar-password'
            );
        }

        $userId = SessionManager::userId();

        if ($userId === null) {
            SessionManager::destroy();

            Response::redirect('/login');
        }

        $result = $passwordService
            ->changeOwnPassword(
                userId: $userId,
                currentPassword:
                    Request::postRawString(
                        'current_password'
                    ),
                newPassword:
                    Request::postRawString(
                        'new_password'
                    ),
                confirmation:
                    Request::postRawString(
                        'password_confirmation'
                    ),
                ipAddress:
                    Request::ipAddress(),
                userAgent:
                    Request::userAgent()
            );

        Csrf::clearAll();

        if (!$result->successful) {
            SessionManager::flash(
                'error',
                $result->message
            );

            foreach ($result->errors as $error) {
                SessionManager::flash(
                    'error',
                    (string) $error
                );
            }

            Response::redirect(
                '/cambiar-password'
            );
        }

        SessionManager::flash(
            'success',
            $result->message
        );

        Response::redirect('/panel');
    }

    if ($method === 'GET' && $path === '/panel') {
        Authorization::requirePasswordChanged();

        View::render(
            'dashboard/index',
            [
                'title' => 'Panel',
            ]
        );

        exit;
    }

    if ($method === 'POST' && $path === '/logout') {
        Authorization::requireAuthenticated();

        if (
            !Csrf::validate(
                'logout',
                Request::postRawString('_token')
            )
        ) {
            SessionManager::flash(
                'error',
                'La solicitud de cierre no es válida.'
            );

            Response::redirect('/');
        }

        $auth->logout(
            reason: 'CIERRE_USUARIO',
            ipAddress: Request::ipAddress(),
            userAgent: Request::userAgent()
        );

        SessionManager::start();

        SessionManager::flash(
            'success',
            'La sesión se cerró correctamente.'
        );

        Response::redirect('/login');
    }

    View::render(
        'errors/404',
        [
            'title' => 'Página no encontrada',
        ],
        404
    );
} catch (\Throwable $exception) {
    error_log(
        sprintf(
            '[Inventario TI] %s en %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    View::render(
        'errors/500',
        [
            'title' => 'Error interno',
        ],
        500
    );
}