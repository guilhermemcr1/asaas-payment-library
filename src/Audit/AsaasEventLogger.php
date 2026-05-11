<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Audit;

use AsaasBiblioteca\Infrastructure\EventLogRepository;
use PDOException;
use Throwable;

final class AsaasEventLogger
{
    private ?EventLogRepository $repository;

    public function __construct(?EventLogRepository $repository = null)
    {
        $this->repository = $repository;
    }

    public function log(array $event): void
    {
        $payloadRaw = $this->sanitizePayload($event['payload_raw'] ?? '');
        $headerToken = $this->maskToken((string) ($event['header_token_recebido'] ?? ''));
        $dataCriacao = date('Y-m-d H:i:s');
        $eventId = trim((string) ($event['event_id'] ?? ''));
        if ($eventId === '') {
            $eventId = 'log_' . $this->generateUuidV4();
        }

        if ($this->repository instanceof EventLogRepository) {
            try {
                $this->repository->insert([
                    'event_id' => $eventId,
                    'tipo_evento' => (string) ($event['tipo_evento'] ?? ''),
                    'txid' => (string) ($event['txid'] ?? ''),
                    'fatura_id' => $event['fatura_id'] ?? null,
                    'situacao_processamento' => (string) ($event['situacao_processamento'] ?? 'recebido'),
                    'payload_raw' => $payloadRaw,
                    'ip_requisicao' => (string) ($event['ip_requisicao'] ?? ''),
                    'app_origem' => (string) ($event['app_origem'] ?? 'webhook_asaas'),
                    'header_token_recebido' => $headerToken,
                    'mensagem_erro' => (string) ($event['mensagem_erro'] ?? ''),
                    'data_evento_gateway' => (string) ($event['data_evento_gateway'] ?? $dataCriacao),
                    'data_criacao' => $dataCriacao,
                ]);
            } catch (PDOException $exception) {
                $this->logPersistenceFailure($eventId, $exception);
            } catch (Throwable $exception) {
                $this->logPersistenceFailure($eventId, $exception);
            }
            return;
        }

        $line = json_encode([
            'ts' => $dataCriacao,
            'event' => $event,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($line) || $line === '') {
            return;
        }

        error_log('[AsaasEventLogger] ' . $line);
    }

    private function logPersistenceFailure(string $eventId, Throwable $exception): void
    {
        error_log(sprintf(
            '[AsaasEventLogger] Falha ao persistir evento %s: %s',
            $eventId,
            $exception->getMessage()
        ));
    }

    private function sanitizePayload($payload): string
    {
        if (is_array($payload) || is_object($payload)) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($json)) {
                return $json;
            }
        }

        return trim((string) $payload);
    }

    private function maskToken(string $token): string
    {
        if ($token === '') {
            return '';
        }

        $len = strlen($token);
        if ($len <= 6) {
            return str_repeat('*', $len);
        }

        return substr($token, 0, 3) . str_repeat('*', $len - 6) . substr($token, -3);
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
