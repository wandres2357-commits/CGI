<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Authorization;
use App\Auth\SessionManager;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Security\Csrf;
use App\Services\SiteLocationService;
use JsonException;

final class SiteLocationController
{
    public function __construct(
        private readonly SiteLocationService $service
    ) {
    }

    public function handle(string $method, string $path): bool
    {
        $routes = [
            'GET /admin/sedes' => 'sitesIndex',
            'GET /admin/sedes/crear' => 'siteCreate',
            'POST /admin/sedes/crear' => 'siteStore',
            'GET /admin/sedes/editar' => 'siteEdit',
            'POST /admin/sedes/editar' => 'siteUpdate',
            'POST /admin/sedes/estado' => 'siteChangeState',
            'GET /admin/ubicaciones' => 'locationsIndex',
            'GET /admin/ubicaciones/crear' => 'locationCreate',
            'POST /admin/ubicaciones/crear' => 'locationStore',
            'GET /admin/ubicaciones/editar' => 'locationEdit',
            'POST /admin/ubicaciones/editar' => 'locationUpdate',
            'POST /admin/ubicaciones/estado' => 'locationChangeState',
            'GET /catalogos/sedes' => 'sitesCatalog',
            'GET /catalogos/tipos-ubicacion' => 'locationTypesCatalog',
            'GET /catalogos/ubicaciones' => 'locationsCatalog',
        ];

        $handler = $routes[$method . ' ' . $path] ?? null;
        if ($handler === null) {
            return false;
        }

        $this->{$handler}();
        return true;
    }

    private function sitesIndex(): never
    {
        Authorization::requireRole('ADMINISTRADOR');

        $search = Request::queryString('q');
        $state = $this->stateFilter(Request::queryString('estado'));
        $page = max(1, Request::queryInt('pagina', 1));
        $perPage = $this->perPage(Request::queryInt('por_pagina', 20));
        $total = $this->service->countSites($search, $state);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        View::render('admin/sites/index', [
            'title' => 'Administración de sedes',
            'sites' => $this->service->listSites($search, $state, $perPage, ($page - 1) * $perPage),
            'filters' => ['q' => $search, 'estado' => $this->stateFilterValue($state), 'por_pagina' => $perPage],
            'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total],
        ]);
        exit;
    }

    private function siteCreate(): never
    {
        Authorization::requireRole('ADMINISTRADOR');
        $this->renderSiteForm('admin/sites/create', 'Crear sede', $this->emptySiteForm(), []);
    }

    private function siteStore(): never
    {
        Authorization::requireRole('ADMINISTRADOR');
        $this->requireCsrf('admin_create_site', '/admin/sedes/crear');
        $actorUserId = $this->actorUserId();
        $form = $this->siteForm();

        $result = $this->service->createSite(
            $actorUserId,
            $form['codigo'],
            $form['nombre'],
            $form['descripcion'],
            $form['direccion'],
            $form['ciudad'],
            $form['departamento'],
            Request::ipAddress(),
            Request::userAgent()
        );
        Csrf::clear('admin_create_site');

        if (!$result['successful']) {
            $this->renderSiteForm('admin/sites/create', 'Crear sede', $form, $this->resultErrors($result), 422);
        }

        SessionManager::flash('success', (string) $result['message']);
        Response::redirect('/admin/sedes');
    }

    private function siteEdit(): never
    {
        Authorization::requireRole('ADMINISTRADOR');
        $siteId = $this->requiredQueryId();
        $site = $this->service->getSite($siteId);
        if ($site === null) {
            $this->notFound('La sede solicitada no existe.', '/admin/sedes');
        }

        $this->renderSiteForm('admin/sites/edit', 'Editar sede', $this->siteToForm($site), [], 200, $site);
    }

    private function siteUpdate(): never
    {
        Authorization::requireRole('ADMINISTRADOR');
        $siteId = $this->requiredPostId();
        $this->requireCsrf('admin_edit_site_' . $siteId, '/admin/sedes/editar?id=' . $siteId);
        $actorUserId = $this->actorUserId();
        $form = $this->siteForm();

        $result = $this->service->updateSite(
            $actorUserId,
            $siteId,
            $form['codigo'],
            $form['nombre'],
            $form['descripcion'],
            $form['direccion'],
            $form['ciudad'],
            $form['departamento'],
            Request::ipAddress(),
            Request::userAgent()
        );
        Csrf::clear('admin_edit_site_' . $siteId);

        if (!$result['successful']) {
            $site = $this->service->getSite($siteId);
            if ($site === null) {
                $this->notFound('La sede solicitada no existe.', '/admin/sedes');
            }
            $this->renderSiteForm('admin/sites/edit', 'Editar sede', $form, $this->resultErrors($result), 422, $site);
        }

        SessionManager::flash('success', (string) $result['message']);
        Response::redirect('/admin/sedes');
    }

    private function siteChangeState(): never
    {
        Authorization::requireRole('ADMINISTRADOR');
        $siteId = $this->requiredPostId();
        $this->requireCsrf('admin_site_state_' . $siteId, '/admin/sedes');

        $result = $this->service->changeSiteState(
            $this->actorUserId(),
            $siteId,
            Request::postString('active') === '1',
            Request::ipAddress(),
            Request::userAgent()
        );
        Csrf::clear('admin_site_state_' . $siteId);
        SessionManager::flash($result['successful'] ? 'success' : 'error', (string) $result['message']);
        Response::redirect('/admin/sedes');
    }

    private function locationsIndex(): never
    {
        Authorization::requireRole('ADMINISTRADOR');

        $search = Request::queryString('q');
        $siteId = $this->positiveOrNull(Request::queryInt('sede_id'));
        $typeId = $this->positiveOrNull(Request::queryInt('tipo_id'));
        $state = $this->stateFilter(Request::queryString('estado'));
        $page = max(1, Request::queryInt('pagina', 1));
        $perPage = $this->perPage(Request::queryInt('por_pagina', 20));
        $total = $this->service->countLocations($search, $siteId, $typeId, $state);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        View::render('admin/locations/index', [
            'title' => 'Administración de ubicaciones',
            'locations' => $this->service->listLocations($search, $siteId, $typeId, $state, null, $perPage, ($page - 1) * $perPage),
            'sites' => $this->service->activeSites(),
            'types' => $this->service->activeLocationTypes(),
            'filters' => [
                'q' => $search,
                'sede_id' => $siteId,
                'tipo_id' => $typeId,
                'estado' => $this->stateFilterValue($state),
                'por_pagina' => $perPage,
            ],
            'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total],
        ]);
        exit;
    }

    private function locationCreate(): never
    {
        Authorization::requireRole('ADMINISTRADOR');
        $form = $this->emptyLocationForm();
        $form['sede_id'] = (string) max(0, Request::queryInt('sede_id'));
        $this->renderLocationForm('admin/locations/create', 'Crear ubicación', $form, []);
    }

    private function locationStore(): never
    {
        Authorization::requireRole('ADMINISTRADOR');
        $this->requireCsrf('admin_create_location', '/admin/ubicaciones/crear');
        $actorUserId = $this->actorUserId();
        $form = $this->locationForm();

        $result = $this->service->createLocation(
            $actorUserId,
            (int) $form['sede_id'],
            (int) $form['tipo_id'],
            $this->positiveOrNull((int) $form['ubicacion_padre_id']),
            $form['codigo'],
            $form['nombre'],
            $form['detalle'],
            Request::ipAddress(),
            Request::userAgent()
        );
        Csrf::clear('admin_create_location');

        if (!$result['successful']) {
            $this->renderLocationForm('admin/locations/create', 'Crear ubicación', $form, $this->resultErrors($result), 422);
        }

        SessionManager::flash('success', (string) $result['message']);
        Response::redirect('/admin/ubicaciones');
    }

    private function locationEdit(): never
    {
        Authorization::requireRole('ADMINISTRADOR');
        $locationId = $this->requiredQueryId();
        $location = $this->service->getLocation($locationId);
        if ($location === null) {
            $this->notFound('La ubicación solicitada no existe.', '/admin/ubicaciones');
        }

        $this->renderLocationForm('admin/locations/edit', 'Editar ubicación', $this->locationToForm($location), [], 200, $location);
    }

    private function locationUpdate(): never
    {
        Authorization::requireRole('ADMINISTRADOR');
        $locationId = $this->requiredPostId();
        $this->requireCsrf('admin_edit_location_' . $locationId, '/admin/ubicaciones/editar?id=' . $locationId);
        $actorUserId = $this->actorUserId();
        $form = $this->locationForm();

        $result = $this->service->updateLocation(
            $actorUserId,
            $locationId,
            (int) $form['sede_id'],
            (int) $form['tipo_id'],
            $this->positiveOrNull((int) $form['ubicacion_padre_id']),
            $form['codigo'],
            $form['nombre'],
            $form['detalle'],
            Request::ipAddress(),
            Request::userAgent()
        );
        Csrf::clear('admin_edit_location_' . $locationId);

        if (!$result['successful']) {
            $location = $this->service->getLocation($locationId);
            if ($location === null) {
                $this->notFound('La ubicación solicitada no existe.', '/admin/ubicaciones');
            }
            $this->renderLocationForm('admin/locations/edit', 'Editar ubicación', $form, $this->resultErrors($result), 422, $location);
        }

        SessionManager::flash('success', (string) $result['message']);
        Response::redirect('/admin/ubicaciones');
    }

    private function locationChangeState(): never
    {
        Authorization::requireRole('ADMINISTRADOR');
        $locationId = $this->requiredPostId();
        $this->requireCsrf('admin_location_state_' . $locationId, '/admin/ubicaciones');

        $result = $this->service->changeLocationState(
            $this->actorUserId(),
            $locationId,
            Request::postString('active') === '1',
            Request::ipAddress(),
            Request::userAgent()
        );
        Csrf::clear('admin_location_state_' . $locationId);
        SessionManager::flash($result['successful'] ? 'success' : 'error', (string) $result['message']);
        Response::redirect('/admin/ubicaciones');
    }

    private function sitesCatalog(): never
    {
        $this->requireCatalogRole();
        $this->json(['data' => $this->service->activeSites()]);
    }

    private function locationTypesCatalog(): never
    {
        $this->requireCatalogRole();
        $this->json(['data' => $this->service->activeLocationTypes()]);
    }

    private function locationsCatalog(): never
    {
        $this->requireCatalogRole();
        $siteId = Request::queryInt('sede_id');
        if ($siteId < 1) {
            $this->json(['error' => 'Debe indicar una sede válida.', 'data' => []], 422);
        }
        $excludeId = $this->positiveOrNull(Request::queryInt('excluir_id'));
        $this->json(['data' => $this->service->activeLocationsBySite($siteId, $excludeId)]);
    }

    private function renderSiteForm(string $view, string $title, array $form, array $errors, int $status = 200, ?array $site = null): never
    {
        View::render($view, ['title' => $title, 'form' => $form, 'errors' => $errors, 'site' => $site], $status);
        exit;
    }

    private function renderLocationForm(string $view, string $title, array $form, array $errors, int $status = 200, ?array $location = null): never
    {
        $siteId = $this->positiveOrNull((int) ($form['sede_id'] ?? 0));
        View::render($view, [
            'title' => $title,
            'form' => $form,
            'errors' => $errors,
            'location' => $location,
            'sites' => $this->service->activeSites(),
            'types' => $this->service->activeLocationTypes(),
            'parents' => $siteId === null ? [] : $this->service->activeLocationsBySite($siteId, $location === null ? null : (int) $location['id']),
        ], $status);
        exit;
    }

    private function requireCatalogRole(): void
    {
        Authorization::requirePasswordChanged();
        if (SessionManager::hasRole('ADMINISTRADOR') || SessionManager::hasRole('TECNICO')) {
            return;
        }
        $this->json(['error' => 'Acceso denegado.'], 403);
    }

    private function requireCsrf(string $form, string $redirect): void
    {
        if (Csrf::validate($form, Request::postRawString('_token'))) {
            return;
        }
        SessionManager::flash('error', 'La solicitud no es válida.');
        Response::redirect($redirect);
    }

    private function actorUserId(): int
    {
        $userId = SessionManager::userId();
        if ($userId !== null) {
            return $userId;
        }
        SessionManager::destroy();
        Response::redirect('/login');
    }

    private function requiredQueryId(): int
    {
        $id = Request::queryInt('id');
        if ($id > 0) {
            return $id;
        }
        $this->notFound('El identificador solicitado no es válido.', '/panel');
    }

    private function requiredPostId(): int
    {
        $raw = Request::postString('id');
        if (preg_match('/^[1-9][0-9]*$/', $raw) === 1) {
            return (int) $raw;
        }
        $this->notFound('El identificador solicitado no es válido.', '/panel');
    }

    private function notFound(string $message, string $redirect): never
    {
        SessionManager::flash('error', $message);
        Response::redirect($redirect);
    }

    private function siteForm(): array
    {
        return [
            'codigo' => strtoupper(Request::postString('codigo')),
            'nombre' => Request::postString('nombre'),
            'descripcion' => Request::postString('descripcion'),
            'direccion' => Request::postString('direccion'),
            'ciudad' => Request::postString('ciudad'),
            'departamento' => Request::postString('departamento'),
        ];
    }

    private function locationForm(): array
    {
        return [
            'sede_id' => Request::postString('sede_id'),
            'tipo_id' => Request::postString('tipo_id'),
            'ubicacion_padre_id' => Request::postString('ubicacion_padre_id'),
            'codigo' => strtoupper(Request::postString('codigo')),
            'nombre' => Request::postString('nombre'),
            'detalle' => Request::postString('detalle'),
        ];
    }

    private function emptySiteForm(): array
    {
        return ['codigo' => '', 'nombre' => '', 'descripcion' => '', 'direccion' => '', 'ciudad' => '', 'departamento' => ''];
    }

    private function emptyLocationForm(): array
    {
        return ['sede_id' => '', 'tipo_id' => '', 'ubicacion_padre_id' => '', 'codigo' => '', 'nombre' => '', 'detalle' => ''];
    }

    private function siteToForm(array $site): array
    {
        return [
            'codigo' => (string) $site['codigo'],
            'nombre' => (string) $site['nombre'],
            'descripcion' => (string) ($site['descripcion'] ?? ''),
            'direccion' => (string) ($site['direccion'] ?? ''),
            'ciudad' => (string) ($site['ciudad'] ?? ''),
            'departamento' => (string) ($site['departamento'] ?? ''),
        ];
    }

    private function locationToForm(array $location): array
    {
        return [
            'sede_id' => (string) $location['sede_id'],
            'tipo_id' => (string) $location['tipo_ubicacion_id'],
            'ubicacion_padre_id' => (string) ($location['ubicacion_padre_id'] ?? ''),
            'codigo' => (string) $location['codigo'],
            'nombre' => (string) $location['nombre'],
            'detalle' => (string) ($location['detalle'] ?? ''),
        ];
    }

    private function resultErrors(array $result): array
    {
        return array_values(array_unique(array_merge([(string) ($result['message'] ?? 'La operación fue rechazada.')], array_map('strval', $result['errors'] ?? []))));
    }

    private function stateFilter(string $value): ?bool
    {
        return match (strtolower($value)) {
            'activo' => true,
            'inactivo' => false,
            default => null,
        };
    }

    private function stateFilterValue(?bool $state): string
    {
        return $state === null ? '' : ($state ? 'activo' : 'inactivo');
    }

    private function perPage(int $value): int
    {
        return in_array($value, [20, 50, 100], true) ? $value : 20;
    }

    private function positiveOrNull(?int $value): ?int
    {
        return $value !== null && $value > 0 ? $value : null;
    }

    private function json(array $payload, int $status = 200): never
    {
        Response::status($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        try {
            echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            Response::status(500);
            echo '{"error":"No fue posible generar la respuesta."}';
        }
        exit;
    }
}
