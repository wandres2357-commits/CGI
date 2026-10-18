<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;
use PDOException;
use Throwable;

final class SiteLocationRepository
{
    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? Connection::get();
    }

    public function connection(): PDO
    {
        return $this->connection;
    }

    public function beginTransaction(): void
    {
        if (!$this->connection->inTransaction()) {
            $this->connection->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    /** @template T @param callable(): T $operation @return T */
    public function transactional(callable $operation): mixed
    {
        $startedHere = !$this->connection->inTransaction();
        if ($startedHere) {
            $this->connection->beginTransaction();
        }
        try {
            $result = $operation();
            if ($startedHere) {
                $this->connection->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($startedHere && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listSites(string $search = '', ?bool $active = null, int $limit = 20, int $offset = 0): array
    {
        $filters = [];
        $parameters = [];
        if ($search !== '') {
            $filters[] = '(s.codigo LIKE :search_code OR s.nombre LIKE :search_name OR s.direccion LIKE :search_address OR s.ciudad LIKE :search_city OR s.departamento LIKE :search_department)';
            $searchValue =
                '%' . $search . '%';

            $parameters[
                'search_code'
            ] = $searchValue;

            $parameters[
                'search_name'
            ] = $searchValue;

            $parameters[
                'search_address'
            ] = $searchValue;

            $parameters[
                'search_city'
            ] = $searchValue;

            $parameters[
                'search_department'
            ] = $searchValue;
        }
        if ($active !== null) {
            $filters[] = 's.activo = :active';
            $parameters['active'] = $active ? 1 : 0;
        }
        $where = $filters === [] ? '' : 'WHERE ' . implode(' AND ', $filters);
        $sql = "SELECT s.id,s.codigo,s.nombre,s.descripcion,s.direccion,s.ciudad,s.departamento,s.activo,s.creado_por,s.creado_en,s.actualizado_en,creator.nombre_usuario AS creado_por_usuario,(SELECT COUNT(*) FROM ubicaciones u WHERE u.sede_id=s.id) AS total_ubicaciones,(SELECT COUNT(*) FROM ubicaciones u WHERE u.sede_id=s.id AND u.activo=1) AS ubicaciones_activas FROM sedes s LEFT JOIN usuarios creator ON creator.id=s.creado_por $where ORDER BY s.activo DESC,s.codigo ASC,s.nombre ASC LIMIT :limit OFFSET :offset";
        $statement = $this->connection->prepare($sql);
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, $name === 'active' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function countSites(string $search = '', ?bool $active = null): int
    {
        $filters = [];
        $parameters = [];
        if ($search !== '') {
            $filters[] = '(codigo LIKE :search_code OR nombre LIKE :search_name OR direccion LIKE :search_address OR ciudad LIKE :search_city OR departamento LIKE :search_department)';
            $searchValue =
                '%' . $search . '%';

            $parameters[
                'search_code'
            ] = $searchValue;

            $parameters[
                'search_name'
            ] = $searchValue;

            $parameters[
                'search_address'
            ] = $searchValue;

            $parameters[
                'search_city'
            ] = $searchValue;

            $parameters[
                'search_department'
            ] = $searchValue;
        }
        if ($active !== null) {
            $filters[] = 'activo = :active';
            $parameters['active'] = $active ? 1 : 0;
        }
        $where = $filters === [] ? '' : 'WHERE ' . implode(' AND ', $filters);
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM sedes ' . $where);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findSiteById(int $siteId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT id,codigo,nombre,descripcion,direccion,ciudad,departamento,activo,creado_por,creado_en,actualizado_en FROM sedes WHERE id=:id';
        if ($forUpdate) { $sql .= ' FOR UPDATE'; }
        $statement = $this->connection->prepare($sql);
        $statement->execute(['id' => $siteId]);
        $site = $statement->fetch();
        return is_array($site) ? $site : null;
    }

    /** @return array<string, mixed>|null */
    public function findSiteByCode(string $code): ?array
    {
        $statement = $this->connection->prepare('SELECT id,codigo,nombre,descripcion,direccion,ciudad,departamento,activo FROM sedes WHERE codigo=:code LIMIT 1');
        $statement->execute(['code' => $code]);
        $site = $statement->fetch();
        return is_array($site) ? $site : null;
    }

    public function siteCodeExists(string $code, ?int $excludeSiteId = null): bool
    {
        return $this->valueExists('sedes', 'codigo', $code, $excludeSiteId);
    }

    public function siteNameExists(string $name, ?int $excludeSiteId = null): bool
    {
        return $this->valueExists('sedes', 'nombre', $name, $excludeSiteId);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeSites(): array
    {
        return $this->connection->query('SELECT id,codigo,nombre,ciudad,departamento FROM sedes WHERE activo=1 ORDER BY codigo ASC,nombre ASC')->fetchAll();
    }

    public function createSite(string $code, string $name, ?string $description, ?string $address, ?string $city, ?string $department, int $createdBy): int
    {
        $statement = $this->connection->prepare('INSERT INTO sedes (codigo,nombre,descripcion,direccion,ciudad,departamento,activo,creado_por) VALUES (:code,:name,:description,:address,:city,:department,1,:created_by)');
        $statement->execute(['code'=>$code,'name'=>$name,'description'=>$description,'address'=>$address,'city'=>$city,'department'=>$department,'created_by'=>$createdBy]);
        return (int) $this->connection->lastInsertId();
    }

    public function updateSite(int $siteId, string $code, string $name, ?string $description, ?string $address, ?string $city, ?string $department): bool
    {
        $statement = $this->connection->prepare('UPDATE sedes SET codigo=:code,nombre=:name,descripcion=:description,direccion=:address,ciudad=:city,departamento=:department WHERE id=:id');
        $statement->execute(['id'=>$siteId,'code'=>$code,'name'=>$name,'description'=>$description,'address'=>$address,'city'=>$city,'department'=>$department]);
        return $statement->rowCount() > 0;
    }

    public function changeSiteState(int $siteId, bool $active): bool
    {
        $statement = $this->connection->prepare('UPDATE sedes SET activo=:active WHERE id=:id');
        $statement->execute(['id'=>$siteId,'active'=>$active ? 1 : 0]);
        return $statement->rowCount() > 0;
    }

    /** @return array<string, int> */
    public function siteDependencies(int $siteId): array
    {
        return [
            'ubicaciones_activas'=>$this->countByReference('ubicaciones','sede_id',$siteId,true),
            'activos'=>$this->countByReference('activos','sede_actual_id',$siteId),
            'personas'=>$this->countByReference('personas','sede_id',$siteId),
            'solicitudes'=>$this->countByReference('solicitudes','sede_destino_id',$siteId),
            'entregas'=>$this->countByReference('entregas','sede_destino_id',$siteId),
            'movimientos_origen'=>$this->countByReference('movimientos_activo','sede_anterior_id',$siteId),
            'movimientos_destino'=>$this->countByReference('movimientos_activo','sede_nueva_id',$siteId),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listLocations(string $search = '', ?int $siteId = null, ?int $typeId = null, ?bool $active = null, ?int $parentId = null, int $limit = 20, int $offset = 0): array
    {
        $filters=[]; $parameters=[];
        if ($search !== '') { $filters[]='(u.codigo LIKE :search_code OR u.nombre LIKE :search_name OR u.detalle LIKE :search_detail OR s.nombre LIKE :search_site OR t.nombre LIKE :search_type)'; $searchValue='%'.$search.'%';$parameters['search_code']=$searchValue;$parameters['search_name']=$searchValue;$parameters['search_detail']=$searchValue;$parameters['search_site']=$searchValue;$parameters['search_type']=$searchValue; }
        if ($siteId !== null) { $filters[]='u.sede_id=:site_id'; $parameters['site_id']=$siteId; }
        if ($typeId !== null) { $filters[]='u.tipo_ubicacion_id=:type_id'; $parameters['type_id']=$typeId; }
        if ($active !== null) { $filters[]='u.activo=:active'; $parameters['active']=$active?1:0; }
        if ($parentId !== null) { $filters[]='u.ubicacion_padre_id=:parent_id'; $parameters['parent_id']=$parentId; }
        $where=$filters===[]?'':'WHERE '.implode(' AND ',$filters);
        $sql="SELECT u.id,u.sede_id,s.codigo AS sede_codigo,s.nombre AS sede_nombre,u.tipo_ubicacion_id,t.codigo AS tipo_codigo,t.nombre AS tipo_nombre,u.ubicacion_padre_id,parent.codigo AS padre_codigo,parent.nombre AS padre_nombre,u.codigo,u.nombre,u.detalle,u.activo,u.creado_por,u.creado_en,u.actualizado_en,creator.nombre_usuario AS creado_por_usuario,(SELECT COUNT(*) FROM ubicaciones child WHERE child.ubicacion_padre_id=u.id) AS total_hijas,(SELECT COUNT(*) FROM ubicaciones child WHERE child.ubicacion_padre_id=u.id AND child.activo=1) AS hijas_activas FROM ubicaciones u INNER JOIN sedes s ON s.id=u.sede_id INNER JOIN tipos_ubicacion t ON t.id=u.tipo_ubicacion_id LEFT JOIN ubicaciones parent ON parent.id=u.ubicacion_padre_id LEFT JOIN usuarios creator ON creator.id=u.creado_por $where ORDER BY s.codigo ASC,u.activo DESC,COALESCE(parent.nombre,'') ASC,u.codigo ASC,u.nombre ASC LIMIT :limit OFFSET :offset";
        $statement=$this->connection->prepare($sql);
        foreach($parameters as $name=>$value){$statement->bindValue(':'.$name,$value,in_array($name,['site_id','type_id','active','parent_id'],true)?PDO::PARAM_INT:PDO::PARAM_STR);}
        $statement->bindValue(':limit',max(1,$limit),PDO::PARAM_INT);
        $statement->bindValue(':offset',max(0,$offset),PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function countLocations(string $search = '', ?int $siteId = null, ?int $typeId = null, ?bool $active = null): int
    {
        $filters=[]; $parameters=[];
        if($search!==''){$filters[]='(u.codigo LIKE :search_code OR u.nombre LIKE :search_name OR u.detalle LIKE :search_detail OR s.nombre LIKE :search_site OR t.nombre LIKE :search_type)';$searchValue='%'.$search.'%';$parameters['search_code']=$searchValue;$parameters['search_name']=$searchValue;$parameters['search_detail']=$searchValue;$parameters['search_site']=$searchValue;$parameters['search_type']=$searchValue;}
        if($siteId!==null){$filters[]='u.sede_id=:site_id';$parameters['site_id']=$siteId;}
        if($typeId!==null){$filters[]='u.tipo_ubicacion_id=:type_id';$parameters['type_id']=$typeId;}
        if($active!==null){$filters[]='u.activo=:active';$parameters['active']=$active?1:0;}
        $where=$filters===[]?'':'WHERE '.implode(' AND ',$filters);
        $statement=$this->connection->prepare('SELECT COUNT(*) FROM ubicaciones u INNER JOIN sedes s ON s.id=u.sede_id INNER JOIN tipos_ubicacion t ON t.id=u.tipo_ubicacion_id '.$where);
        $statement->execute($parameters);
        return (int)$statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findLocationById(int $locationId, bool $forUpdate = false): ?array
    {
        $sql='SELECT u.id,u.sede_id,s.codigo AS sede_codigo,s.nombre AS sede_nombre,s.activo AS sede_activa,u.tipo_ubicacion_id,t.codigo AS tipo_codigo,t.nombre AS tipo_nombre,t.activo AS tipo_activo,u.ubicacion_padre_id,parent.sede_id AS padre_sede_id,parent.codigo AS padre_codigo,parent.nombre AS padre_nombre,parent.activo AS padre_activo,u.codigo,u.nombre,u.detalle,u.activo,u.creado_por,u.creado_en,u.actualizado_en FROM ubicaciones u INNER JOIN sedes s ON s.id=u.sede_id INNER JOIN tipos_ubicacion t ON t.id=u.tipo_ubicacion_id LEFT JOIN ubicaciones parent ON parent.id=u.ubicacion_padre_id WHERE u.id=:id';
        if($forUpdate){$sql.=' FOR UPDATE';}
        $statement=$this->connection->prepare($sql);$statement->execute(['id'=>$locationId]);$location=$statement->fetch();
        return is_array($location)?$location:null;
    }

    public function locationCodeExists(int $siteId, string $code, ?int $excludeLocationId = null): bool
    {
        $sql='SELECT COUNT(*) FROM ubicaciones WHERE sede_id=:site_id AND codigo=:code';
        $parameters=['site_id'=>$siteId,'code'=>$code];
        if($excludeLocationId!==null){$sql.=' AND id<>:exclude_id';$parameters['exclude_id']=$excludeLocationId;}
        $statement=$this->connection->prepare($sql);$statement->execute($parameters);
        return (int)$statement->fetchColumn()>0;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeLocationTypes(): array
    {
        return $this->connection->query('SELECT id,codigo,nombre,descripcion FROM tipos_ubicacion WHERE activo=1 ORDER BY nombre ASC,codigo ASC')->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findLocationTypeById(int $typeId, bool $forUpdate = false): ?array
    {
        $sql='SELECT id,codigo,nombre,descripcion,activo FROM tipos_ubicacion WHERE id=:id';
        if($forUpdate){$sql.=' FOR UPDATE';}
        $statement=$this->connection->prepare($sql);$statement->execute(['id'=>$typeId]);$type=$statement->fetch();
        return is_array($type)?$type:null;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeLocationsBySite(int $siteId, ?int $excludeId = null): array
    {
        $sql='SELECT u.id,u.codigo,u.nombre,u.tipo_ubicacion_id,t.nombre AS tipo_nombre,u.ubicacion_padre_id FROM ubicaciones u INNER JOIN tipos_ubicacion t ON t.id=u.tipo_ubicacion_id WHERE u.sede_id=:site_id AND u.activo=1';
        $parameters=['site_id'=>$siteId];
        if($excludeId!==null){$sql.=' AND u.id<>:exclude_id';$parameters['exclude_id']=$excludeId;}
        $sql.=' ORDER BY u.codigo ASC,u.nombre ASC';
        $statement=$this->connection->prepare($sql);$statement->execute($parameters);
        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function childrenOf(int $locationId, bool $onlyActive = false): array
    {
        $sql='SELECT id,sede_id,tipo_ubicacion_id,ubicacion_padre_id,codigo,nombre,detalle,activo FROM ubicaciones WHERE ubicacion_padre_id=:location_id';
        if($onlyActive){$sql.=' AND activo=1';}
        $sql.=' ORDER BY codigo ASC,nombre ASC';
        $statement=$this->connection->prepare($sql);$statement->execute(['location_id'=>$locationId]);
        return $statement->fetchAll();
    }

    public function hasActiveChildren(int $locationId): bool
    {
        return $this->countActiveChildren($locationId)>0;
    }

    public function wouldCreateCycle(int $locationId, int $candidateParentId): bool
    {
        if($locationId===$candidateParentId){return true;}
        $statement=$this->connection->prepare('WITH RECURSIVE descendants AS (SELECT id,ubicacion_padre_id FROM ubicaciones WHERE ubicacion_padre_id=:location_id UNION ALL SELECT child.id,child.ubicacion_padre_id FROM ubicaciones child INNER JOIN descendants parent ON child.ubicacion_padre_id=parent.id) SELECT COUNT(*) FROM descendants WHERE id=:candidate_parent_id');
        $statement->execute(['location_id'=>$locationId,'candidate_parent_id'=>$candidateParentId]);
        return (int)$statement->fetchColumn()>0;
    }

    public function createLocation(int $siteId, int $typeId, ?int $parentId, string $code, string $name, ?string $detail, int $createdBy): int
    {
        $statement=$this->connection->prepare('INSERT INTO ubicaciones (sede_id,tipo_ubicacion_id,ubicacion_padre_id,codigo,nombre,detalle,activo,creado_por) VALUES (:site_id,:type_id,:parent_id,:code,:name,:detail,1,:created_by)');
        $statement->execute(['site_id'=>$siteId,'type_id'=>$typeId,'parent_id'=>$parentId,'code'=>$code,'name'=>$name,'detail'=>$detail,'created_by'=>$createdBy]);
        return (int)$this->connection->lastInsertId();
    }

    public function updateLocation(int $locationId, int $siteId, int $typeId, ?int $parentId, string $code, string $name, ?string $detail): bool
    {
        $statement=$this->connection->prepare('UPDATE ubicaciones SET sede_id=:site_id,tipo_ubicacion_id=:type_id,ubicacion_padre_id=:parent_id,codigo=:code,nombre=:name,detalle=:detail WHERE id=:id');
        $statement->execute(['id'=>$locationId,'site_id'=>$siteId,'type_id'=>$typeId,'parent_id'=>$parentId,'code'=>$code,'name'=>$name,'detail'=>$detail]);
        return $statement->rowCount()>0;
    }

    public function changeLocationState(int $locationId, bool $active): bool
    {
        $statement=$this->connection->prepare('UPDATE ubicaciones SET activo=:active WHERE id=:id');
        $statement->execute(['id'=>$locationId,'active'=>$active?1:0]);
        return $statement->rowCount()>0;
    }

    /** @return array<string, int> */
    public function locationDependencies(int $locationId): array
    {
        return [
            'hijas_activas'=>$this->countActiveChildren($locationId),
            'activos'=>$this->countByReference('activos','ubicacion_actual_id',$locationId),
            'personas'=>$this->countByReference('personas','ubicacion_id',$locationId),
            'entregas'=>$this->countByReference('entregas','ubicacion_destino_id',$locationId),
            'movimientos_origen'=>$this->countByReference('movimientos_activo','ubicacion_anterior_id',$locationId),
            'movimientos_destino'=>$this->countByReference('movimientos_activo','ubicacion_nueva_id',$locationId),
        ];
    }

    private function countActiveChildren(int $locationId): int
    {
        $statement=$this->connection->prepare('SELECT COUNT(*) FROM ubicaciones WHERE ubicacion_padre_id=:location_id AND activo=1');
        $statement->execute(['location_id'=>$locationId]);
        return (int)$statement->fetchColumn();
    }

    private function valueExists(string $table, string $column, string $value, ?int $excludeId): bool
    {
        $allowed=['sedes.codigo','sedes.nombre'];
        $reference=$table.'.'.$column;
        if(!in_array($reference,$allowed,true)){throw new PDOException('Referencia de unicidad no permitida: '.$reference);}
        $sql=sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s`=:value',$table,$column);
        $parameters=['value'=>$value];
        if($excludeId!==null){$sql.=' AND id<>:exclude_id';$parameters['exclude_id']=$excludeId;}
        $statement=$this->connection->prepare($sql);$statement->execute($parameters);
        return (int)$statement->fetchColumn()>0;
    }

    private function countByReference(string $table, string $column, int $value, bool $onlyActive = false): int
    {
        $allowed=['ubicaciones.sede_id','activos.sede_actual_id','personas.sede_id','solicitudes.sede_destino_id','entregas.sede_destino_id','movimientos_activo.sede_anterior_id','movimientos_activo.sede_nueva_id','activos.ubicacion_actual_id','personas.ubicacion_id','entregas.ubicacion_destino_id','movimientos_activo.ubicacion_anterior_id','movimientos_activo.ubicacion_nueva_id'];
        $reference=$table.'.'.$column;
        if(!in_array($reference,$allowed,true)){throw new PDOException('Referencia de dependencia no permitida: '.$reference);}
        $sql=sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s`=:value',$table,$column);
        if($onlyActive){$sql.=' AND `activo`=1';}
        $statement=$this->connection->prepare($sql);$statement->execute(['value'=>$value]);
        return (int)$statement->fetchColumn();
    }
}