<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Infrastructure;

use PDO;
use PDOException;

final class IdempotencyRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function claimEventId(string $eventId): bool
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO asaas_fila_processamento (id, event_id, created_at) VALUES (:id, :event_id, :created_at)');
            $stmt->execute([
                ':id' => $this->generateUuidV4(),
                ':event_id' => $eventId,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (PDOException $exception) {
            if ($this->isDuplicateEventIdException($exception)) {
                return false;
            }

            throw $exception;
        }
    }

    private function isDuplicateEventIdException(PDOException $exception): bool
    {
        if ((string) $exception->getCode() === '23000') {
            return true;
        }

        $message = strtolower($exception->getMessage());
        return str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint')
            || str_contains($message, 'uniq_asaas_fila_event');
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
