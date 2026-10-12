<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class SessionRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function create(
        string $sessionId,
        int $userId,
        string $expiresAt,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        if ($sessionId === '') {
            throw new RuntimeException(
                'El identificador de sesión está vacío.'
            );
        }

        if ($userId <= 0) {
            throw new RuntimeException(
                'El identificador de usuario no es válido.'
            );
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO sesiones_usuario (
                id,
                usuario_id,
                direccion_ip,
                agente_usuario,
                iniciada_en,
                ultima_actividad_en,
                expira_en,
                cerrada_en,
                motivo_cierre
             ) VALUES (
                :id,
                :usuario_id,
                :direccion_ip,
                :agente_usuario,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP,
                :expira_en,
                NULL,
                NULL
             )'
        );

        $statement->execute([
            'id' => $sessionId,
            'usuario_id' => $userId,
            'direccion_ip' => $this->limit(
                $ipAddress,
                45
            ),
            'agente_usuario' => $this->limit(
                $userAgent,
                500
            ),
            'expira_en' => $expiresAt,
        ]);
    }

    public function close(
        string $sessionId,
        string $reason
    ): void {
        if ($sessionId === '') {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE sesiones_usuario
             SET cerrada_en = CURRENT_TIMESTAMP,
                 motivo_cierre = :motivo
             WHERE id = :id
               AND cerrada_en IS NULL'
        );

        $statement->execute([
            'motivo' => $this->limitRequired(
                $reason,
                100
            ),
            'id' => $sessionId,
        ]);
    }

    public function touch(
        string $sessionId
    ): void {
        if ($sessionId === '') {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE sesiones_usuario
             SET ultima_actividad_en =
                 CURRENT_TIMESTAMP
             WHERE id = :id
               AND cerrada_en IS NULL
               AND expira_en > CURRENT_TIMESTAMP'
        );

        $statement->execute([
            'id' => $sessionId,
        ]);
    }

    public function closeExpiredForUser(
        int $userId
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE sesiones_usuario
             SET cerrada_en = CURRENT_TIMESTAMP,
                 motivo_cierre =
                     \'EXPIRADA_AUTOMATICAMENTE\'
             WHERE usuario_id = :usuario_id
               AND cerrada_en IS NULL
               AND expira_en <= CURRENT_TIMESTAMP'
        );

        $statement->execute([
            'usuario_id' => $userId,
        ]);
    }

    public function countActiveForUser(
        int $userId
    ): int {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM sesiones_usuario
             WHERE usuario_id = :usuario_id
               AND cerrada_en IS NULL
               AND expira_en > CURRENT_TIMESTAMP'
        );

        $statement->execute([
            'usuario_id' => $userId,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function closeOldestUntilLimit(
        int $userId,
        int $maximumSessions,
        string $currentSessionId
    ): array {
        $maximumSessions = max(
            1,
            $maximumSessions
        );

        $this->closeExpiredForUser($userId);

        $activeCount = $this->countActiveForUser(
            $userId
        );

        $sessionsToClose = max(
            0,
            $activeCount - $maximumSessions
        );

        if ($sessionsToClose === 0) {
            return [];
        }

        $sql =
            'SELECT id
             FROM sesiones_usuario
             WHERE usuario_id = :usuario_id
               AND cerrada_en IS NULL
               AND expira_en > CURRENT_TIMESTAMP
               AND id <> :sesion_actual
             ORDER BY iniciada_en ASC, id ASC
             LIMIT '
            . (int) $sessionsToClose;

        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            'usuario_id' => $userId,
            'sesion_actual' => $currentSessionId,
        ]);

        $sessionIds = $statement->fetchAll(
            PDO::FETCH_COLUMN
        );

        if (!is_array($sessionIds)) {
            return [];
        }

        foreach ($sessionIds as $sessionId) {
            $this->close(
                (string) $sessionId,
                'LIMITE_SESIONES'
            );
        }

        return array_map(
            'strval',
            $sessionIds
        );
    }

    public function closeOtherSessions(
        int $userId,
        string $currentSessionId,
        string $reason
    ): int {
        $statement = $this->pdo->prepare(
            'UPDATE sesiones_usuario
             SET cerrada_en = CURRENT_TIMESTAMP,
                 motivo_cierre = :motivo
             WHERE usuario_id = :usuario_id
               AND id <> :sesion_actual
               AND cerrada_en IS NULL'
        );

        $statement->execute([
            'motivo' => $this->limitRequired(
                $reason,
                100
            ),
            'usuario_id' => $userId,
            'sesion_actual' => $currentSessionId,
        ]);

        return $statement->rowCount();
    }

    public function closeAllForUser(
        int $userId,
        string $reason
    ): int {
        $statement = $this->pdo->prepare(
            'UPDATE sesiones_usuario
             SET cerrada_en = CURRENT_TIMESTAMP,
                 motivo_cierre = :motivo
             WHERE usuario_id = :usuario_id
               AND cerrada_en IS NULL'
        );

        $statement->execute([
            'motivo' => $this->limitRequired(
                $reason,
                100
            ),
            'usuario_id' => $userId,
        ]);

        return $statement->rowCount();
    }

    public function isActive(
        string $sessionId,
        int $userId
    ): bool {
        if ($sessionId === '' || $userId <= 0) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM sesiones_usuario
             WHERE id = :id
               AND usuario_id = :usuario_id
               AND cerrada_en IS NULL
               AND expira_en > CURRENT_TIMESTAMP'
        );

        $statement->execute([
            'id' => $sessionId,
            'usuario_id' => $userId,
        ]);

        return (int) $statement->fetchColumn() === 1;
    }

    public function replaceSessionId(
        string $oldSessionId,
        string $newSessionId,
        int $userId
    ): void {
        if (
            $oldSessionId === ''
            || $newSessionId === ''
            || $userId <= 0
        ) {
            throw new RuntimeException(
                'No fue posible validar la rotación de sesión.'
            );
        }

        if (hash_equals($oldSessionId, $newSessionId)) {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE sesiones_usuario
             SET id = :nuevo_id,
                 ultima_actividad_en =
                    CURRENT_TIMESTAMP
             WHERE id = :id_anterior
               AND usuario_id = :usuario_id
               AND cerrada_en IS NULL
               AND expira_en > CURRENT_TIMESTAMP'
        );

        $statement->execute([
            'nuevo_id' => $newSessionId,
            'id_anterior' => $oldSessionId,
            'usuario_id' => $userId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'No fue posible actualizar '
                . 'el identificador de sesión.'
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
                'El motivo de cierre es obligatorio.'
            );
        }

        return mb_substr(
            $value,
            0,
            $maximumLength
        );
    }
}