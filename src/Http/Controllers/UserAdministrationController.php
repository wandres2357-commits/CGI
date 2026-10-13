<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Authorization;
use App\Auth\SessionManager;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Security\Csrf;
use App\Services\UserAdministrationService;
use RuntimeException;

final class UserAdministrationController
{
    public function __construct(
        private readonly UserAdministrationService $service
    ) {
    }

    public function handle(
        string $method,
        string $path
    ): bool {
        if (
            $method === 'GET'
            && $path === '/administracion/usuarios'
        ) {
            $this->index();

            return true;
        }

        if (
            $method === 'GET'
            && $path ===
                '/administracion/usuarios/crear'
        ) {
            $this->create();

            return true;
        }

        if (
            $method === 'POST'
            && $path ===
                '/administracion/usuarios/crear'
        ) {
            $this->store();

            return true;
        }

        if (
            $method === 'GET'
            && $path ===
                '/administracion/usuarios/password-temporal'
        ) {
            $this->showTemporaryPassword();

            return true;
        }
                if (
            preg_match(
                '#^/administracion/usuarios/([1-9][0-9]*)/editar$#',
                $path,
                $matches
            ) === 1
        ) {
            $userId = (int) $matches[1];

            if ($method === 'GET') {
                $this->edit($userId);

                return true;
            }

            if ($method === 'POST') {
                $this->update($userId);

                return true;
            }
        }

        if (
            $method === 'POST'
            && preg_match(
                '#^/administracion/usuarios/([1-9][0-9]*)/estado$#',
                $path,
                $matches
            ) === 1
        ) {
            $this->changeState(
                (int) $matches[1]
            );

            return true;
        }

        return false;
    }

    private function index(): never
    {
        Authorization::requireRole(
            'ADMINISTRADOR'
        );

        $search = Request::queryString('q');

        $role = strtoupper(
            Request::queryString('rol')
        );

        if (
            !in_array(
                $role,
                [
                    'ADMINISTRADOR',
                    'TECNICO',
                ],
                true
            )
        ) {
            $role = '';
        }

        $state = strtolower(
            Request::queryString('estado')
        );

        if (
            !in_array(
                $state,
                [
                    'activo',
                    'inactivo',
                    'bloqueado',
                ],
                true
            )
        ) {
            $state = '';
        }

        $page = max(
            1,
            Request::queryInt(
                'pagina',
                1
            )
        );

        $perPage = Request::queryInt(
            'por_pagina',
            20
        );

        if (
            !in_array(
                $perPage,
                [20, 50, 100],
                true
            )
        ) {
            $perPage = 20;
        }

        $result = $this->service->listUsers(
            search: $search,
            role: $role !== ''
                ? $role
                : null,
            state: $state !== ''
                ? $state
                : null,
            page: $page,
            perPage: $perPage
        );

        View::render(
            'admin/users/index',
            [
                'title' =>
                    'Administración de usuarios',
                'result' => $result,
                'roles' =>
                    $this->service
                        ->activeRoles(),
            ]
        );

        exit;
    }

    private function create(): never
    {
        Authorization::requireRole(
            'ADMINISTRADOR'
        );

        View::render(
            'admin/users/create',
            [
                'title' => 'Crear usuario',
                'roles' =>
                    $this->service
                        ->activeRoles(),
                'form' => [
                    'nombre_usuario' => '',
                    'nombre_completo' => '',
                    'correo' => '',
                    'rol' => 'TECNICO',
                ],
                'errors' => [],
            ]
        );

        exit;
    }

    private function store(): never
    {
        Authorization::requireRole(
            'ADMINISTRADOR'
        );

        if (
            !Csrf::validate(
                'admin_create_user',
                Request::postRawString('_token')
            )
        ) {
            SessionManager::flash(
                'error',
                'La solicitud no es válida.'
            );

            Response::redirect(
                '/administracion/usuarios/crear'
            );
        }

        $actorUserId =
            SessionManager::userId();

        if ($actorUserId === null) {
            SessionManager::destroy();

            Response::redirect('/login');
        }

        $form = [
            'nombre_usuario' =>
                strtolower(
                    Request::postString(
                        'nombre_usuario'
                    )
                ),
            'nombre_completo' =>
                Request::postString(
                    'nombre_completo'
                ),
            'correo' =>
                strtolower(
                    Request::postString(
                        'correo'
                    )
                ),
            'rol' => strtoupper(
                Request::postString('rol')
            ),
        ];

        $actorPassword =
            Request::postRawString(
                'actor_password'
            );

        $result = $this->service->createUser(
            actorUserId: $actorUserId,
            username:
                $form['nombre_usuario'],
            fullName:
                $form['nombre_completo'],
            email: $form['correo'],
            roleCode: $form['rol'],
            actorPassword:
                $actorPassword,
            ipAddress:
                Request::ipAddress(),
            userAgent:
                Request::userAgent()
        );

        if (
            $actorPassword !== ''
            && function_exists(
                'sodium_memzero'
            )
        ) {
            sodium_memzero(
                $actorPassword
            );
        }

        $actorPassword = '';

        Csrf::clear(
            'admin_create_user'
        );

        if (!$result['successful']) {
            View::render(
                'admin/users/create',
                [
                    'title' =>
                        'Crear usuario',
                    'roles' =>
                        $this->service
                            ->activeRoles(),
                    'form' => $form,
                    'errors' => array_merge(
                        [
                            (string) $result[
                                'message'
                            ],
                        ],
                        array_map(
                            'strval',
                            $result['errors']
                                ?? []
                        )
                    ),
                ],
                422
            );

            exit;
        }

        $temporaryPassword =
            (string) (
                $result[
                    'temporary_password'
                ]
                ?? ''
            );

        if ($temporaryPassword === '') {
            throw new RuntimeException(
                'No fue posible recuperar '
                . 'la contraseña temporal.'
            );
        }

        $createdUser =
            $this->service->getUser(
                (int) $result['user_id']
            );

        if ($createdUser === null) {
            throw new RuntimeException(
                'No fue posible recuperar '
                . 'el usuario creado.'
            );
        }

        SessionManager::start();

        $_SESSION[
            'temporary_password_once'
        ] = [
            'password' =>
                $temporaryPassword,
            'user_id' =>
                (int) $createdUser['id'],
            'username' =>
                (string) $createdUser[
                    'nombre_usuario'
                ],
            'full_name' =>
                (string) $createdUser[
                    'nombre_completo'
                ],
            'role' =>
                (string) $createdUser[
                    'rol_codigo'
                ],
            'created_at' => time(),
        ];

        $temporaryPassword = '';

        Response::redirect(
            '/administracion/usuarios/password-temporal'
        );
    }

    private function showTemporaryPassword(): never
    {
        Authorization::requireRole(
            'ADMINISTRADOR'
        );

        header(
            'Cache-Control: no-store, '
            . 'no-cache, must-revalidate, '
            . 'max-age=0'
        );

        header('Pragma: no-cache');

        SessionManager::start();

        $temporaryData =
            $_SESSION[
                'temporary_password_once'
            ] ?? null;

        unset(
            $_SESSION[
                'temporary_password_once'
            ]
        );

        if (!is_array($temporaryData)) {
            SessionManager::flash(
                'warning',
                'La contraseña temporal '
                . 'ya no está disponible.'
            );

            Response::redirect(
                '/administracion/usuarios'
            );
        }

        $createdAt = (int) (
            $temporaryData[
                'created_at'
            ] ?? 0
        );

        if (
            $createdAt <= 0
            || time() - $createdAt > 300
        ) {
            if (
                isset(
                    $temporaryData[
                        'password'
                    ]
                )
                && is_string(
                    $temporaryData[
                        'password'
                    ]
                )
                && function_exists(
                    'sodium_memzero'
                )
            ) {
                sodium_memzero(
                    $temporaryData[
                        'password'
                    ]
                );
            }

            SessionManager::flash(
                'warning',
                'La contraseña temporal expiró.'
            );

            Response::redirect(
                '/administracion/usuarios'
            );
        }

        View::render(
            'admin/users/temporary-password',
            [
                'title' =>
                    'Contraseña temporal',
                'temporaryData' =>
                    $temporaryData,
            ]
        );

        exit;
    }

    private function edit(
        int $userId
    ): never {
        Authorization::requireRole(
            'ADMINISTRADOR'
        );

        $user = $this->service->getUser(
            $userId
        );

        if ($user === null) {
            SessionManager::flash(
                'error',
                'El usuario solicitado no existe.'
            );

            Response::redirect(
                '/administracion/usuarios'
            );
        }

        View::render(
            'admin/users/edit',
            [
                'title' => 'Editar usuario',
                'user' => $user,
                'roles' =>
                    $this->service
                        ->activeRoles(),
                'form' => [
                    'nombre_usuario' =>
                        $user['nombre_usuario'],
                    'nombre_completo' =>
                        $user['nombre_completo'],
                    'correo' =>
                        $user['correo'],
                    'rol' =>
                        $user['rol_codigo'],
                ],
                'errors' => [],
            ]
        );

        exit;
    }

        private function update(
        int $userId
    ): never {
        Authorization::requireRole(
            'ADMINISTRADOR'
        );

        if (
            !Csrf::validate(
                'admin_edit_user_' . $userId,
                Request::postRawString('_token')
            )
        ) {
            SessionManager::flash(
                'error',
                'La solicitud no es válida.'
            );

            Response::redirect(
                '/administracion/usuarios/'
                . $userId
                . '/editar'
            );
        }

        $actorUserId =
            SessionManager::userId();

        if ($actorUserId === null) {
            SessionManager::destroy();

            Response::redirect('/login');
        }

        $form = [
            'nombre_usuario' =>
                strtolower(
                    Request::postString(
                        'nombre_usuario'
                    )
                ),
            'nombre_completo' =>
                Request::postString(
                    'nombre_completo'
                ),
            'correo' =>
                strtolower(
                    Request::postString(
                        'correo'
                    )
                ),
            'rol' => strtoupper(
                Request::postString('rol')
            ),
        ];

        $actorPassword =
            Request::postRawString(
                'actor_password'
            );

        $result = $this->service->updateUser(
            actorUserId: $actorUserId,
            targetUserId: $userId,
            username:
                $form['nombre_usuario'],
            fullName:
                $form['nombre_completo'],
            email:
                $form['correo'],
            roleCode:
                $form['rol'],
            actorPassword:
                $actorPassword,
            ipAddress:
            private function changeState(
        int $userId
    ): never {
        Authorization::requireRole(
            'ADMINISTRADOR'
        );

        if (
            !Csrf::validate(
                'admin_user_state_' . $userId,
                Request::postRawString('_token')
            )
        ) {
            SessionManager::flash(
                'error',
                'La solicitud no es válida.'
            );

            Response::redirect(
                '/administracion/usuarios'
            );
        }

        $actorUserId =
            SessionManager::userId();

        if ($actorUserId === null) {
            SessionManager::destroy();

            Response::redirect('/login');
        }

        $active = Request::postString(
            'active'
        ) === '1';

        $actorPassword =
            Request::postRawString(
                'actor_password'
            );

        $result =
            $this->service
                ->changeUserState(
                    actorUserId:
                        $actorUserId,
                    targetUserId:
                        $userId,
                    active: $active,
                    actorPassword:
                        $actorPassword,
                    ipAddress:
                        Request::ipAddress(),
                    userAgent:
                        Request::userAgent()
                );

        if (
            $actorPassword !== ''
            && function_exists(
                'sodium_memzero'
            )
        ) {
            sodium_memzero(
                $actorPassword
            );
        }

        $actorPassword = '';

        Csrf::clear(
            'admin_user_state_' . $userId
        );

        SessionManager::flash(
            $result['successful']
                ? 'success'
                : 'error',
            (string) $result['message']
        );

        Response::redirect(
            '/administracion/usuarios'
        );
    }
}