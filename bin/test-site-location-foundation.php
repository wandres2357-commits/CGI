<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Services\SiteLocationService;
use PDO;
use RuntimeException;
use Throwable;

require dirname(__DIR__) . '/bootstrap/bootstrap.php';

$pdo = Connection::get();
$service = new SiteLocationService();
$passed = 0;
$failed = 0;
$transactionStarted = false;

$assert = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo '[OK] ', $message, PHP_EOL;
        return;
    }

    $failed++;
    echo '[ERROR] ', $message, PHP_EOL;
};

$successful = static fn (array $result): bool => ($result['successful'] ?? false) === true;
$rejected = static fn (array $result): bool => ($result['successful'] ?? true) === false;

$scalar = static function (PDO $pdo, string $sql, array $parameters = []): int {
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return (int) $statement->fetchColumn();
};

try {
    echo 'Prueba fundacional de sedes y ubicaciones', PHP_EOL;
    echo str_repeat('=', 46), PHP_EOL;

    $actorStatement = $pdo->query(
        "SELECT u.id
         FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         WHERE u.activo = 1
           AND r.codigo = 'ADMINISTRADOR'
         ORDER BY u.id
         LIMIT 1"
    );
    $actorUserId = (int) $actorStatement->fetchColumn();

    if ($actorUserId < 1) {
        throw new RuntimeException('No existe un administrador activo para ejecutar la prueba.');
    }

    $typeStatement = $pdo->query(
        "SELECT id
         FROM tipos_ubicacion
         WHERE activo = 1
         ORDER BY id
         LIMIT 1"
    );
    $typeId = (int) $typeStatement->fetchColumn();

    if ($typeId < 1) {
        throw new RuntimeException('No existe un tipo de ubicación activo para ejecutar la prueba.');
    }

    $baselineSites = $scalar($pdo, 'SELECT COUNT(*) FROM sedes');
    $baselineLocations = $scalar($pdo, 'SELECT COUNT(*) FROM ubicaciones');
    $baselineAuditRows = $scalar($pdo, 'SELECT COUNT(*) FROM auditoria');

    $suffix = strtoupper(substr(hash('sha256', getmypid() . '|' . microtime(true)), 0, 10));
    $siteCodeA = 'TST-A-' . $suffix;
    $siteCodeB = 'TST-B-' . $suffix;
    $siteNameA = 'Sede temporal A ' . $suffix;
    $siteNameB = 'Sede temporal B ' . $suffix;
    $locationCodeRootA = 'ROOT-A-' . $suffix;
    $locationCodeChildA = 'CHILD-A-' . $suffix;
    $locationCodeRootB = 'ROOT-B-' . $suffix;

    $pdo->beginTransaction();
    $transactionStarted = true;

    $createSiteA = $service->createSite(
        $actorUserId,
        $siteCodeA,
        $siteNameA,
        'Registro temporal para prueba reversible.',
        null,
        'Ciudad prueba',
        'Departamento prueba',
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($successful($createSiteA), 'Crea la sede temporal A.');
    $siteIdA = (int) ($createSiteA['site_id'] ?? 0);

    $createSiteB = $service->createSite(
        $actorUserId,
        $siteCodeB,
        $siteNameB,
        'Registro temporal para prueba reversible.',
        null,
        null,
        null,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($successful($createSiteB), 'Crea la sede temporal B.');
    $siteIdB = (int) ($createSiteB['site_id'] ?? 0);

    $assert($siteIdA > 0 && $siteIdB > 0 && $siteIdA !== $siteIdB, 'Obtiene identificadores distintos para las dos sedes.');

    $duplicateSite = $service->createSite(
        $actorUserId,
        $siteCodeA,
        'Sede con código duplicado ' . $suffix,
        null,
        null,
        null,
        null,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($rejected($duplicateSite), 'Rechaza un código de sede duplicado.');

    $createRootA = $service->createLocation(
        $actorUserId,
        $siteIdA,
        $typeId,
        null,
        $locationCodeRootA,
        'Ubicación raíz A ' . $suffix,
        'Registro temporal reversible.',
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($successful($createRootA), 'Crea una ubicación raíz en la sede A.');
    $rootAId = (int) ($createRootA['location_id'] ?? 0);

    $createChildA = $service->createLocation(
        $actorUserId,
        $siteIdA,
        $typeId,
        $rootAId,
        $locationCodeChildA,
        'Ubicación hija A ' . $suffix,
        null,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($successful($createChildA), 'Crea una ubicación hija en la misma sede.');
    $childAId = (int) ($createChildA['location_id'] ?? 0);

    $createRootB = $service->createLocation(
        $actorUserId,
        $siteIdB,
        $typeId,
        null,
        $locationCodeRootB,
        'Ubicación raíz B ' . $suffix,
        null,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($successful($createRootB), 'Crea una ubicación raíz en la sede B.');
    $rootBId = (int) ($createRootB['location_id'] ?? 0);

    $duplicateLocation = $service->createLocation(
        $actorUserId,
        $siteIdA,
        $typeId,
        null,
        $locationCodeRootA,
        'Ubicación con código duplicado ' . $suffix,
        null,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($rejected($duplicateLocation), 'Rechaza un código de ubicación duplicado dentro de la sede.');

    $crossSiteParent = $service->createLocation(
        $actorUserId,
        $siteIdB,
        $typeId,
        $rootAId,
        'CROSS-' . $suffix,
        'Ubicación con padre de otra sede ' . $suffix,
        null,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($rejected($crossSiteParent), 'Rechaza una ubicación padre perteneciente a otra sede.');

    $selfParent = $service->updateLocation(
        $actorUserId,
        $rootAId,
        $siteIdA,
        $typeId,
        $rootAId,
        $locationCodeRootA,
        'Ubicación raíz A ' . $suffix,
        null,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($rejected($selfParent), 'Rechaza que una ubicación sea su propio padre.');

    $cycle = $service->updateLocation(
        $actorUserId,
        $rootAId,
        $siteIdA,
        $typeId,
        $childAId,
        $locationCodeRootA,
        'Ubicación raíz A ' . $suffix,
        null,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($rejected($cycle), 'Rechaza un ciclo jerárquico.');

    $deactivateRootWithChild = $service->changeLocationState(
        $actorUserId,
        $rootAId,
        false,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($rejected($deactivateRootWithChild), 'Bloquea la desactivación de una ubicación con hijas activas.');

    $deactivateSiteWithLocations = $service->changeSiteState(
        $actorUserId,
        $siteIdA,
        false,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($rejected($deactivateSiteWithLocations), 'Bloquea la desactivación de una sede con ubicaciones activas.');

    $deactivateChild = $service->changeLocationState(
        $actorUserId,
        $childAId,
        false,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($successful($deactivateChild), 'Desactiva una ubicación sin dependencias operativas.');

    $deactivateRoot = $service->changeLocationState(
        $actorUserId,
        $rootAId,
        false,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($successful($deactivateRoot), 'Desactiva la ubicación padre después de inactivar sus hijas.');

    $deactivateSite = $service->changeSiteState(
        $actorUserId,
        $siteIdA,
        false,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($successful($deactivateSite), 'Desactiva la sede cuando ya no tiene dependencias operativas activas.');

    $reactivateSite = $service->changeSiteState(
        $actorUserId,
        $siteIdA,
        true,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($successful($reactivateSite), 'Reactiva la sede temporal.');

    $reactivateRoot = $service->changeLocationState(
        $actorUserId,
        $rootAId,
        true,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($successful($reactivateRoot), 'Reactiva la ubicación raíz.');

    $reactivateChild = $service->changeLocationState(
        $actorUserId,
        $childAId,
        true,
        '127.0.0.1',
        'test-site-location-foundation'
    );
    $assert($successful($reactivateChild), 'Reactiva la ubicación hija cuando su sede y padre están activos.');

    $scopedAuditRows = $scalar(
        $pdo,
        "SELECT COUNT(*)
         FROM auditoria
         WHERE modulo = 'SEDES_UBICACIONES'
           AND (
                entidad_id IN (:site_a, :site_b, :root_a, :child_a, :root_b)
                OR agente_usuario = 'test-site-location-foundation'
           )",
        [
            'site_a' => $siteIdA,
            'site_b' => $siteIdB,
            'root_a' => $rootAId,
            'child_a' => $childAId,
            'root_b' => $rootBId,
        ]
    );
    $assert($scopedAuditRows > 0, 'Registra auditoría de operaciones exitosas y rechazadas.');

    $sensitiveAuditRows = $scalar(
        $pdo,
        "SELECT COUNT(*)
         FROM auditoria
         WHERE modulo = 'SEDES_UBICACIONES'
           AND agente_usuario = 'test-site-location-foundation'
           AND CONCAT_WS(' ', COALESCE(valores_anteriores, ''), COALESCE(valores_nuevos, ''))
               REGEXP 'password|password_hash|temporary_password|actor_password|_token'"
    );
    $assert($sensitiveAuditRows === 0, 'La auditoría no contiene claves sensibles.');

    $assert(
        $scalar($pdo, 'SELECT COUNT(*) FROM sedes') === $baselineSites + 2,
        'La transacción contiene exactamente dos sedes temporales antes del rollback.'
    );
    $assert(
        $scalar($pdo, 'SELECT COUNT(*) FROM ubicaciones') === $baselineLocations + 3,
        'La transacción contiene exactamente tres ubicaciones temporales antes del rollback.'
    );
} catch (Throwable $exception) {
    $failed++;
    echo '[ERROR] Excepción no controlada: ', $exception->getMessage(), PHP_EOL;
} finally {
    if ($transactionStarted && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo '[OK] Rollback final ejecutado.', PHP_EOL;
    }
}

try {
    $finalSites = $scalar($pdo, 'SELECT COUNT(*) FROM sedes');
    $finalLocations = $scalar($pdo, 'SELECT COUNT(*) FROM ubicaciones');
    $finalAuditRows = $scalar($pdo, 'SELECT COUNT(*) FROM auditoria');

    $assert($finalSites === ($baselineSites ?? $finalSites), 'El total de sedes regresó al valor inicial.');
    $assert($finalLocations === ($baselineLocations ?? $finalLocations), 'El total de ubicaciones regresó al valor inicial.');
    $assert($finalAuditRows === ($baselineAuditRows ?? $finalAuditRows), 'La auditoría de prueba fue revertida.');

    if (isset($siteCodeA, $siteCodeB)) {
        $remainingSites = $scalar(
            $pdo,
            'SELECT COUNT(*) FROM sedes WHERE codigo IN (:code_a, :code_b)',
            ['code_a' => $siteCodeA, 'code_b' => $siteCodeB]
        );
        $assert($remainingSites === 0, 'No quedaron sedes temporales después del rollback.');
    }
} catch (Throwable $exception) {
    $failed++;
    echo '[ERROR] No fue posible verificar el estado final: ', $exception->getMessage(), PHP_EOL;
}

echo str_repeat('-', 46), PHP_EOL;
echo 'Pruebas aprobadas: ', $passed, PHP_EOL;
echo 'Pruebas fallidas: ', $failed, PHP_EOL;

if ($failed > 0) {
    exit(1);
}

echo 'Resultado final: APROBADO', PHP_EOL;
exit(0);
