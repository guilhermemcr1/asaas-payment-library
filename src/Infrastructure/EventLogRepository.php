<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Infrastructure;

use PDO;

final class EventLogRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function insert(array $event): void
    {
        $sql = 'INSERT INTO asaas_event_log (
            id, event_id, tipo_evento, txid, fatura_id, situacao_processamento,
            payload_raw, ip_requisicao, app_origem, header_token_recebido,
            mensagem_erro, data_evento_gateway, data_criacao
        ) VALUES (
            :id, :event_id, :tipo_evento, :txid, :fatura_id, :situacao_processamento,
            :payload_raw, :ip_requisicao, :app_origem, :header_token_recebido,
            :mensagem_erro, :data_evento_gateway, :data_criacao
        ) ON DUPLICATE KEY UPDATE
            situacao_processamento = VALUES(situacao_processamento),
            mensagem_erro = VALUES(mensagem_erro),
            data_evento_gateway = VALUES(data_evento_gateway)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $this->generateUuidV4(),
            ':event_id' => (string) ($event['event_id'] ?? ''),
            ':tipo_evento' => (string) ($event['tipo_evento'] ?? ''),
            ':txid' => (string) ($event['txid'] ?? ''),
            ':fatura_id' => $event['fatura_id'] ?? null,
            ':situacao_processamento' => (string) ($event['situacao_processamento'] ?? 'recebido'),
            ':payload_raw' => (string) ($event['payload_raw'] ?? ''),
            ':ip_requisicao' => (string) ($event['ip_requisicao'] ?? ''),
            ':app_origem' => (string) ($event['app_origem'] ?? 'webhook_asaas'),
            ':header_token_recebido' => (string) ($event['header_token_recebido'] ?? ''),
            ':mensagem_erro' => (string) ($event['mensagem_erro'] ?? ''),
            ':data_evento_gateway' => (string) ($event['data_evento_gateway'] ?? date('Y-m-d H:i:s')),
            ':data_criacao' => (string) ($event['data_criacao'] ?? date('Y-m-d H:i:s')),
        ]);
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
