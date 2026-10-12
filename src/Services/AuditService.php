<?php

declare(strict_types=1);

namespace App\Services;

use JsonException;
use PDO;
use RuntimeException;

final class AuditService
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function record(
        ?int $userId,
        string $module,
        string $action,
        string $result,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $correlationId = null
    ): void {
        $module = strtoupper(
            trim($module)
        );

        $action = strtoupper(
            trim($action)
        );

        $result = strtoupper(
            trim($result)
        );

        if ($module === '') {
            throw new RuntimeException(
                'El módulo de auditoría es obligatorio.'
            );
        }

        if ($action === '') {
            throw new RuntimeException(
                'La acción de auditoría es obligatoria.'
            );
        }

        if (
            !in_array(
                $result,
                ['EXITOSO', 'FALLIDO'],
                true
            )
        ) {
            throw new RuntimeException(
                'El resultado de auditoría no es válido.'
            );
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO auditoria (
                usuario_id,
                fecha_evento,
                direccion_ip,
                agente_usuario,
                modulo,
                accion,
                entidad_tipo,
                entidad_id,
                valores_anteriores,
                valores_nuevos,
                resultado,
                motivo,
                id_correlacion
             ) VALUES (
                :usuario_id,
                CURRENT_TIMESTAMP(6),
                :direccion_ip,
                :agente_usuario,
                :modulo,
                :accion,
                :entidad_tipo,
                :entidad_id,
                :valores_anteriores,
                :valores_nuevos,
                :resultado,
                :motivo,
                :id_correlacion
             )'
        );

        $statement->execute([
            'usuario_id' => $userId,
            'direccion_ip' => $this->limit(
                $ipAddress,
                45
            ),
            'agente_usuario' => $this->limit(
                $userAgent,
                500
            ),
            'modulo' => $this->limitRequired(
                $module,
                80
            ),
            'accion' => $this->limitRequired(
                $action,
                80
            ),
            'entidad_tipo' => $this->limit(
                $entityType !== null
                    ? strtoupper(
                        trim($entityType)
                    )
                    : null,
                80
            ),
            'entidad_id' => $entityId,
            'valores_anteriores' =>
                $this->encodeJson($oldValues),
            'valores_nuevos' =>
                $this->encodeJson($newValues),
            'resultado' => $result,
            'motivo' => $this->limit(
                $reason,
                500
            ),
            'id_correlacion' => $this->limit(
                $correlationId,
                36
            ),
        ]);
    }

    public function recordAuthentication(
        ?int $userId,
        string $action,
        string $result,
        string $reason,
        ?string $identifier = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $newValues = [];

        if ($identifier !== null) {
            $newValues['identificador_hash'] = hash(
                'sha256',
                strtolower(
                    trim($identifier)
                )
            );
        }

        $this->record(
            userId: $userId,
            module: 'AUTENTICACION',
            action: $action,
            result: $result,
            entityType: $userId !== null
                ? 'USUARIO'
                : null,
            entityId: $userId,
            newValues: $newValues !== []
                ? $newValues
                : null,
            reason: $reason,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }

    private function encodeJson(
        ?array $values
    ): ?string {
        if ($values === null) {
            return null;
        }

        try {
            return json_encode(
                $values,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'No fue posible serializar los datos de auditoría.',
                0,
                $exception
            );
        }
    }

    private function limit(
        ?string $value,
        int $maximumLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr(
            $value,
            0,
            $maximumLength
        );
    }

    private function limitRequired(
        string $value,
        int $maximumLength
    ): string {
        $value = trim($value);

        if ($value === '') {
            throw new RuntimeException(
                'El valor obligatorio de auditoría está vacío.'
            );
        }

        return mb_substr(
            $value,
            0,
            $maximumLength
        );
    }
}