<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SiteLocationRepository;
use JsonException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class SiteLocationService
{
    private SiteLocationRepository $repository;
    private PDO $connection;

    public function __construct(?SiteLocationRepository $repository = null)
    {
        $this->repository = $repository ?? new SiteLocationRepository();
        $this->connection = $this->repository->connection();
    }

    /** @return array<int, array<string, mixed>> */
    public function listSites(string $search = '', ?bool $active = null, int $limit = 20, int $offset = 0): array
    {
        return $this->repository->listSites(trim($search), $active, $this->normalizeLimit($limit), max(0, $offset));
    }

    public function countSites(string $search = '', ?bool $active = null): int
    {
        return $this->repository->countSites(trim($search), $active);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeSites(): array
    {
        return $this->repository->activeSites();
    }

    /** @return array<string, mixed>|null */
    public function getSite(int $siteId): ?array
    {
        return $siteId > 0 ? $this->repository->findSiteById($siteId) : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function listLocations(string $search = '', ?int $siteId = null, ?int $typeId = null, ?bool $active = null, ?int $parentId = null, int $limit = 20, int $offset = 0): array
    {
        return $this->repository->listLocations(
            trim($search),
            $this->positiveOrNull($siteId),
            $this->positiveOrNull($typeId),
            $active,
            $this->positiveOrNull($parentId),
            $this->normalizeLimit($limit),
            max(0, $offset)
        );
    }

    public function countLocations(string $search = '', ?int $siteId = null, ?int $typeId = null, ?bool $active = null): int
    {
        return $this->repository->countLocations(
            trim($search),
            $this->positiveOrNull($siteId),
            $this->positiveOrNull($typeId),
            $active
        );
    }

    /** @return array<string, mixed>|null */
    public function getLocation(int $locationId): ?array
    {
        return $locationId > 0 ? $this->repository->findLocationById($locationId) : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeLocationTypes(): array
    {
        return $this->repository->activeLocationTypes();
    }

    /** @return array<int, array<string, mixed>> */
    public function activeLocationsBySite(int $siteId, ?int $excludeId = null): array
    {
        if ($siteId < 1) {
            return [];
        }
        return $this->repository->activeLocationsBySite($siteId, $this->positiveOrNull($excludeId));
    }

    /**
     * @return array{successful: bool, message: string, errors: array<int, string>, site_id?: int}
     */
    public function createSite(
        int $actorUserId,
        string $code,
        string $name,
        ?string $description,
        ?string $address,
        ?string $city,
        ?string $department,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $data = $this->normalizeSiteData($code, $name, $description, $address, $city, $department);
        $errors = $this->validateSiteData($data);
        if ($actorUserId < 1) {
            $errors[] = 'El usuario que realiza la operación no es válido.';
        }
        if ($errors !== []) {
            $this->auditFailure($actorUserId, 'CREAR_SEDE', 'SEDE', null, 'VALIDACION_FALLIDA', $errors, $ipAddress, $userAgent);
            return $this->failure('No fue posible crear la sede.', $errors);
        }

        try {
            $siteId = $this->repository->transactional(function () use ($actorUserId, $data, $ipAddress, $userAgent): int {
                if ($this->repository->siteCodeExists($data['code'])) {
                    throw new RuntimeException('El código de sede ya está registrado.');
                }
                if ($this->repository->siteNameExists($data['name'])) {
                    throw new RuntimeException('El nombre de la sede ya está registrado.');
                }
                $siteId = $this->repository->createSite(
                    $data['code'], $data['name'], $data['description'], $data['address'],
                    $data['city'], $data['department'], $actorUserId
                );
                $after = $this->repository->findSiteById($siteId, true);
                $this->writeAudit($actorUserId, 'CREAR_SEDE', 'SEDE', $siteId, null, $after, 'EXITOSO', 'CREACION_ADMINISTRATIVA', $ipAddress, $userAgent);
                return $siteId;
            });
            return ['successful' => true, 'message' => 'La sede fue creada correctamente.', 'errors' => [], 'site_id' => $siteId];
        } catch (Throwable $exception) {
            return $this->handleWriteException($exception, $actorUserId, 'CREAR_SEDE', 'SEDE', null, $ipAddress, $userAgent);
        }
    }

    /** @return array{successful: bool, message: string, errors: array<int, string>} */
    public function updateSite(
        int $actorUserId,
        int $siteId,
        string $code,
        string $name,
        ?string $description,
        ?string $address,
        ?string $city,
        ?string $department,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $data = $this->normalizeSiteData($code, $name, $description, $address, $city, $department);
        $errors = $this->validateSiteData($data);
        if ($actorUserId < 1 || $siteId < 1) {
            $errors[] = 'La operación solicitada no es válida.';
        }
        if ($errors !== []) {
            $this->auditFailure($actorUserId, 'EDITAR_SEDE', 'SEDE', $siteId > 0 ? $siteId : null, 'VALIDACION_FALLIDA', $errors, $ipAddress, $userAgent);
            return $this->failure('No fue posible actualizar la sede.', $errors);
        }

        try {
            $this->repository->transactional(function () use ($actorUserId, $siteId, $data, $ipAddress, $userAgent): void {
                $before = $this->repository->findSiteById($siteId, true);
                if ($before === null) {
                    throw new RuntimeException('La sede solicitada no existe.');
                }
                if ($this->repository->siteCodeExists($data['code'], $siteId)) {
                    throw new RuntimeException('El código de sede ya está registrado.');
                }
                if ($this->repository->siteNameExists($data['name'], $siteId)) {
                    throw new RuntimeException('El nombre de la sede ya está registrado.');
                }
                $this->repository->updateSite(
                    $siteId, $data['code'], $data['name'], $data['description'],
                    $data['address'], $data['city'], $data['department']
                );
                $after = $this->repository->findSiteById($siteId, true);
                $this->writeAudit($actorUserId, 'EDITAR_SEDE', 'SEDE', $siteId, $before, $after, 'EXITOSO', 'ACTUALIZACION_ADMINISTRATIVA', $ipAddress, $userAgent);
            });
            return ['successful' => true, 'message' => 'La sede fue actualizada correctamente.', 'errors' => []];
        } catch (Throwable $exception) {
            return $this->handleWriteException($exception, $actorUserId, 'EDITAR_SEDE', 'SEDE', $siteId, $ipAddress, $userAgent);
        }
    }

    /** @return array{successful: bool, message: string, errors: array<int, string>} */
    public function changeSiteState(int $actorUserId, int $siteId, bool $active, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        if ($actorUserId < 1 || $siteId < 1) {
            return $this->failure('No fue posible cambiar el estado de la sede.', ['La operación solicitada no es válida.']);
        }
        try {
            $this->repository->transactional(function () use ($actorUserId, $siteId, $active, $ipAddress, $userAgent): void {
                $before = $this->repository->findSiteById($siteId, true);
                if ($before === null) {
                    throw new RuntimeException('La sede solicitada no existe.');
                }
                if ((bool) $before['activo'] === $active) {
                    throw new RuntimeException($active ? 'La sede ya se encuentra activa.' : 'La sede ya se encuentra inactiva.');
                }
                if (!$active) {
                    $dependencies = $this->repository->siteDependencies($siteId);
                    $blocking = [
                        'ubicaciones activas' => $dependencies['ubicaciones_activas'],
                        'activos actuales' => $dependencies['activos'],
                        'personas asociadas' => $dependencies['personas'],
                    ];
                    $this->assertNoBlockingDependencies($blocking, 'No se puede desactivar la sede porque tiene dependencias operativas: ');
                }
                $this->repository->changeSiteState($siteId, $active);
                $after = $this->repository->findSiteById($siteId, true);
                $this->writeAudit(
                    $actorUserId, 'CAMBIAR_ESTADO_SEDE', 'SEDE', $siteId, $before, $after,
                    'EXITOSO', $active ? 'ACTIVACION_ADMINISTRATIVA' : 'DESACTIVACION_ADMINISTRATIVA',
                    $ipAddress, $userAgent
                );
            });
            return ['successful' => true, 'message' => $active ? 'La sede fue activada correctamente.' : 'La sede fue desactivada correctamente.', 'errors' => []];
        } catch (Throwable $exception) {
            return $this->handleWriteException($exception, $actorUserId, 'CAMBIAR_ESTADO_SEDE', 'SEDE', $siteId, $ipAddress, $userAgent);
        }
    }

    /**
     * @return array{successful: bool, message: string, errors: array<int, string>, location_id?: int}
     */
    public function createLocation(
        int $actorUserId,
        int $siteId,
        int $typeId,
        ?int $parentId,
        string $code,
        string $name,
        ?string $detail,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $data = $this->normalizeLocationData($siteId, $typeId, $parentId, $code, $name, $detail);
        $errors = $this->validateLocationData($data);
        if ($actorUserId < 1) {
            $errors[] = 'El usuario que realiza la operación no es válido.';
        }
        if ($errors !== []) {
            $this->auditFailure($actorUserId, 'CREAR_UBICACION', 'UBICACION', null, 'VALIDACION_FALLIDA', $errors, $ipAddress, $userAgent);
            return $this->failure('No fue posible crear la ubicación.', $errors);
        }

        try {
            $locationId = $this->repository->transactional(function () use ($actorUserId, $data, $ipAddress, $userAgent): int {
                $this->assertLocationReferences($data, null);
                if ($this->repository->locationCodeExists($data['site_id'], $data['code'])) {
                    throw new RuntimeException('El código de ubicación ya existe dentro de la sede.');
                }
                $locationId = $this->repository->createLocation(
                    $data['site_id'], $data['type_id'], $data['parent_id'],
                    $data['code'], $data['name'], $data['detail'], $actorUserId
                );
                $after = $this->repository->findLocationById($locationId, true);
                $this->writeAudit($actorUserId, 'CREAR_UBICACION', 'UBICACION', $locationId, null, $after, 'EXITOSO', 'CREACION_ADMINISTRATIVA', $ipAddress, $userAgent);
                return $locationId;
            });
            return ['successful' => true, 'message' => 'La ubicación fue creada correctamente.', 'errors' => [], 'location_id' => $locationId];
        } catch (Throwable $exception) {
            return $this->handleWriteException($exception, $actorUserId, 'CREAR_UBICACION', 'UBICACION', null, $ipAddress, $userAgent);
        }
    }

    /** @return array{successful: bool, message: string, errors: array<int, string>} */
    public function updateLocation(
        int $actorUserId,
        int $locationId,
        int $siteId,
        int $typeId,
        ?int $parentId,
        string $code,
        string $name,
        ?string $detail,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $data = $this->normalizeLocationData($siteId, $typeId, $parentId, $code, $name, $detail);
        $errors = $this->validateLocationData($data);
        if ($actorUserId < 1 || $locationId < 1) {
            $errors[] = 'La operación solicitada no es válida.';
        }
        if ($errors !== []) {
            $this->auditFailure($actorUserId, 'EDITAR_UBICACION', 'UBICACION', $locationId > 0 ? $locationId : null, 'VALIDACION_FALLIDA', $errors, $ipAddress, $userAgent);
            return $this->failure('No fue posible actualizar la ubicación.', $errors);
        }

        try {
            $this->repository->transactional(function () use ($actorUserId, $locationId, $data, $ipAddress, $userAgent): void {
                $before = $this->repository->findLocationById($locationId, true);
                if ($before === null) {
                    throw new RuntimeException('La ubicación solicitada no existe.');
                }
                $this->assertLocationReferences($data, $locationId);
                if ($this->repository->locationCodeExists($data['site_id'], $data['code'], $locationId)) {
                    throw new RuntimeException('El código de ubicación ya existe dentro de la sede.');
                }
                if ($data['parent_id'] !== null && $this->repository->wouldCreateCycle($locationId, $data['parent_id'])) {
                    throw new RuntimeException('La ubicación padre seleccionada produciría un ciclo jerárquico.');
                }
                $activeChildren = $this->repository->childrenOf($locationId, true);
                if ($activeChildren !== [] && (int) $before['sede_id'] !== $data['site_id']) {
                    throw new RuntimeException('No se puede cambiar la sede de una ubicación que tiene ubicaciones hijas activas.');
                }
                $this->repository->updateLocation(
                    $locationId, $data['site_id'], $data['type_id'], $data['parent_id'],
                    $data['code'], $data['name'], $data['detail']
                );
                $after = $this->repository->findLocationById($locationId, true);
                $this->writeAudit($actorUserId, 'EDITAR_UBICACION', 'UBICACION', $locationId, $before, $after, 'EXITOSO', 'ACTUALIZACION_ADMINISTRATIVA', $ipAddress, $userAgent);
            });
            return ['successful' => true, 'message' => 'La ubicación fue actualizada correctamente.', 'errors' => []];
        } catch (Throwable $exception) {
            return $this->handleWriteException($exception, $actorUserId, 'EDITAR_UBICACION', 'UBICACION', $locationId, $ipAddress, $userAgent);
        }
    }

    /** @return array{successful: bool, message: string, errors: array<int, string>} */
    public function changeLocationState(int $actorUserId, int $locationId, bool $active, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        if ($actorUserId < 1 || $locationId < 1) {
            return $this->failure('No fue posible cambiar el estado de la ubicación.', ['La operación solicitada no es válida.']);
        }
        try {
            $this->repository->transactional(function () use ($actorUserId, $locationId, $active, $ipAddress, $userAgent): void {
                $before = $this->repository->findLocationById($locationId, true);
                if ($before === null) {
                    throw new RuntimeException('La ubicación solicitada no existe.');
                }
                if ((bool) $before['activo'] === $active) {
                    throw new RuntimeException($active ? 'La ubicación ya se encuentra activa.' : 'La ubicación ya se encuentra inactiva.');
                }
                if ($active) {
                    $data = [
                        'site_id' => (int) $before['sede_id'],
                        'type_id' => (int) $before['tipo_ubicacion_id'],
                        'parent_id' => $before['ubicacion_padre_id'] === null ? null : (int) $before['ubicacion_padre_id'],
                        'code' => (string) $before['codigo'],
                        'name' => (string) $before['nombre'],
                        'detail' => $before['detalle'] === null ? null : (string) $before['detalle'],
                    ];
                    $this->assertLocationReferences($data, $locationId);
                } else {
                    $dependencies = $this->repository->locationDependencies($locationId);
                    $blocking = [
                        'ubicaciones hijas activas' => $dependencies['hijas_activas'],
                        'activos actuales' => $dependencies['activos'],
                        'personas asociadas' => $dependencies['personas'],
                    ];
                    $this->assertNoBlockingDependencies($blocking, 'No se puede desactivar la ubicación porque tiene dependencias operativas: ');
                }
                $this->repository->changeLocationState($locationId, $active);
                $after = $this->repository->findLocationById($locationId, true);
                $this->writeAudit(
                    $actorUserId, 'CAMBIAR_ESTADO_UBICACION', 'UBICACION', $locationId, $before, $after,
                    'EXITOSO', $active ? 'ACTIVACION_ADMINISTRATIVA' : 'DESACTIVACION_ADMINISTRATIVA',
                    $ipAddress, $userAgent
                );
            });
            return ['successful' => true, 'message' => $active ? 'La ubicación fue activada correctamente.' : 'La ubicación fue desactivada correctamente.', 'errors' => []];
        } catch (Throwable $exception) {
            return $this->handleWriteException($exception, $actorUserId, 'CAMBIAR_ESTADO_UBICACION', 'UBICACION', $locationId, $ipAddress, $userAgent);
        }
    }

    /** @param array{site_id:int,type_id:int,parent_id:?int,code:string,name:string,detail:?string} $data */
    private function assertLocationReferences(array $data, ?int $locationId): void
    {
        $site = $this->repository->findSiteById($data['site_id'], true);
        if ($site === null || !(bool) $site['activo']) {
            throw new RuntimeException('La sede seleccionada no existe o está inactiva.');
        }
        $type = $this->repository->findLocationTypeById($data['type_id'], true);
        if ($type === null || !(bool) $type['activo']) {
            throw new RuntimeException('El tipo de ubicación seleccionado no existe o está inactivo.');
        }
        if ($data['parent_id'] === null) {
            return;
        }
        if ($locationId !== null && $data['parent_id'] === $locationId) {
            throw new RuntimeException('Una ubicación no puede ser su propia ubicación padre.');
        }
        $parent = $this->repository->findLocationById($data['parent_id'], true);
        if ($parent === null || !(bool) $parent['activo']) {
            throw new RuntimeException('La ubicación padre seleccionada no existe o está inactiva.');
        }
        if ((int) $parent['sede_id'] !== $data['site_id']) {
            throw new RuntimeException('La ubicación padre debe pertenecer a la misma sede.');
        }
    }

    /** @return array{code:string,name:string,description:?string,address:?string,city:?string,department:?string} */
    private function normalizeSiteData(string $code, string $name, ?string $description, ?string $address, ?string $city, ?string $department): array
    {
        return [
            'code' => strtoupper(trim($code)),
            'name' => trim($name),
            'description' => $this->nullableText($description),
            'address' => $this->nullableText($address),
            'city' => $this->nullableText($city),
            'department' => $this->nullableText($department),
        ];
    }

    /** @param array{code:string,name:string,description:?string,address:?string,city:?string,department:?string} $data @return array<int,string> */
    private function validateSiteData(array $data): array
    {
        $errors = [];
        if ($data['code'] === '' || mb_strlen($data['code']) > 30) { $errors[] = 'El código de sede es obligatorio y debe tener máximo 30 caracteres.'; }
        if ($data['name'] === '' || mb_strlen($data['name']) > 120) { $errors[] = 'El nombre de la sede es obligatorio y debe tener máximo 120 caracteres.'; }
        if ($data['description'] !== null && mb_strlen($data['description']) > 255) { $errors[] = 'La descripción debe tener máximo 255 caracteres.'; }
        if ($data['address'] !== null && mb_strlen($data['address']) > 255) { $errors[] = 'La dirección debe tener máximo 255 caracteres.'; }
        if ($data['city'] !== null && mb_strlen($data['city']) > 120) { $errors[] = 'La ciudad debe tener máximo 120 caracteres.'; }
        if ($data['department'] !== null && mb_strlen($data['department']) > 120) { $errors[] = 'El departamento debe tener máximo 120 caracteres.'; }
        return $errors;
    }

    /** @return array{site_id:int,type_id:int,parent_id:?int,code:string,name:string,detail:?string} */
    private function normalizeLocationData(int $siteId, int $typeId, ?int $parentId, string $code, string $name, ?string $detail): array
    {
        return [
            'site_id' => $siteId,
            'type_id' => $typeId,
            'parent_id' => $this->positiveOrNull($parentId),
            'code' => strtoupper(trim($code)),
            'name' => trim($name),
            'detail' => $this->nullableText($detail),
        ];
    }

    /** @param array{site_id:int,type_id:int,parent_id:?int,code:string,name:string,detail:?string} $data @return array<int,string> */
    private function validateLocationData(array $data): array
    {
        $errors = [];
        if ($data['site_id'] < 1) { $errors[] = 'Debe seleccionar una sede válida.'; }
        if ($data['type_id'] < 1) { $errors[] = 'Debe seleccionar un tipo de ubicación válido.'; }
        if ($data['code'] === '' || mb_strlen($data['code']) > 50) { $errors[] = 'El código de ubicación es obligatorio y debe tener máximo 50 caracteres.'; }
        if ($data['name'] === '' || mb_strlen($data['name']) > 160) { $errors[] = 'El nombre de la ubicación es obligatorio y debe tener máximo 160 caracteres.'; }
        if ($data['detail'] !== null && mb_strlen($data['detail']) > 255) { $errors[] = 'El detalle debe tener máximo 255 caracteres.'; }
        return $errors;
    }

    /** @param array<string,int> $dependencies */
    private function assertNoBlockingDependencies(array $dependencies, string $prefix): void
    {
        $messages = [];
        foreach ($dependencies as $label => $count) {
            if ($count > 0) {
                $messages[] = $label . ' (' . $count . ')';
            }
        }
        if ($messages !== []) {
            throw new RuntimeException($prefix . implode(', ', $messages) . '.');
        }
    }

    private function writeAudit(
        ?int $actorUserId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?array $before,
        ?array $after,
        string $result,
        ?string $reason,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $statement = $this->connection->prepare(
            'INSERT INTO auditoria (usuario_id,direccion_ip,agente_usuario,modulo,accion,entidad_tipo,entidad_id,valores_anteriores,valores_nuevos,resultado,motivo) VALUES (:user_id,:ip_address,:user_agent,:module,:action,:entity_type,:entity_id,:before_values,:after_values,:result,:reason)'
        );
        $statement->execute([
            'user_id' => $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            'ip_address' => $this->limitedNullableText($ipAddress, 45),
            'user_agent' => $this->limitedNullableText($userAgent, 500),
            'module' => 'SEDES_UBICACIONES',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_values' => $this->jsonOrNull($before),
            'after_values' => $this->jsonOrNull($after),
            'result' => $result,
            'reason' => $this->limitedNullableText($reason, 500),
        ]);
    }

    /** @param array<int,string> $errors */
    private function auditFailure(?int $actorUserId, string $action, ?string $entityType, ?int $entityId, string $reason, array $errors, ?string $ipAddress, ?string $userAgent): void
    {
        try {
            $this->writeAudit($actorUserId, $action, $entityType, $entityId, null, ['errores' => $errors], 'FALLIDO', $reason, $ipAddress, $userAgent);
        } catch (Throwable) {
            // La auditoría no debe ocultar el error funcional original.
        }
    }

    /** @return array{successful:false,message:string,errors:array<int,string>} */
    private function handleWriteException(Throwable $exception, int $actorUserId, string $action, string $entityType, ?int $entityId, ?string $ipAddress, ?string $userAgent): array
    {
        $message = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Ocurrió un error al guardar la información.';
        if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
            $message = 'La información entra en conflicto con un registro existente o con una relación activa.';
        }
        $this->auditFailure($actorUserId, $action, $entityType, $entityId, 'OPERACION_RECHAZADA', [$message], $ipAddress, $userAgent);
        return $this->failure($message, [$message]);
    }

    /** @return array{successful:false,message:string,errors:array<int,string>} */
    private function failure(string $message, array $errors): array
    {
        return ['successful' => false, 'message' => $message, 'errors' => array_values(array_unique(array_map('strval', $errors)))];
    }

    private function nullableText(?string $value): ?string
    {
        if ($value === null) { return null; }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function limitedNullableText(?string $value, int $limit): ?string
    {
        $value = $this->nullableText($value);
        return $value === null ? null : mb_substr($value, 0, $limit);
    }

    private function positiveOrNull(?int $value): ?int
    {
        return $value !== null && $value > 0 ? $value : null;
    }

    private function normalizeLimit(int $limit): int
    {
        return in_array($limit, [20, 50, 100], true) ? $limit : 20;
    }

    private function jsonOrNull(?array $value): ?string
    {
        if ($value === null) { return null; }
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('No fue posible serializar la auditoría.', 0, $exception);
        }
    }
}
