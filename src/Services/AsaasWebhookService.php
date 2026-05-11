<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Services;

use AsaasBiblioteca\Audit\AsaasEventLogger;
use AsaasBiblioteca\DTO\GatewayResponse;
use AsaasBiblioteca\Infrastructure\IdempotencyRepository;
use AsaasBiblioteca\Mappers\AsaasStatusMapper;
use AsaasBiblioteca\Security\WebhookAuthGuard;
use PDOException;
use Throwable;

final class AsaasWebhookService
{
    private WebhookAuthGuard $authGuard;
    private AsaasEventLogger $logger;
    private AsaasStatusMapper $statusMapper;
    private ?IdempotencyRepository $idempotencyRepository;
    private const LOG_ONLY_EVENTS = [
        'payment_created',
        'payment_updated',
        'payment_authorized',
        'payment_awaiting_risk_analysis',
        'payment_approved_by_risk_analysis',
        'payment_reproved_by_risk_analysis',
        'payment_restored',
        'payment_dunning_received',
        'payment_dunning_requested',
        'payment_bank_slip_viewed',
        'payment_checkout_viewed',
        'payment_split_cancelled',
        'payment_split_divergence_block',
        'payment_split_divergence_block_finished',
        'subscription_created',
        'subscription_updated',
        'subscription_inactivated',
        'subscription_deleted',
        'subscription_split_disabled',
        'subscription_split_divergence_block',
        'subscription_split_divergence_block_finished',
    ];

    public function __construct(
        WebhookAuthGuard $authGuard,
        AsaasEventLogger $logger,
        AsaasStatusMapper $statusMapper,
        ?IdempotencyRepository $idempotencyRepository = null
    )
    {
        $this->authGuard = $authGuard;
        $this->logger = $logger;
        $this->statusMapper = $statusMapper;
        $this->idempotencyRepository = $idempotencyRepository;
    }

    public function processWebhook(string $rawBody, array $headers, string $remoteIp, string $httpMethod = 'POST'): array
    {
        $method = strtoupper(trim($httpMethod));
        if ($method !== 'POST') {
            return [
                'status_code' => 405,
                'payload' => GatewayResponse::error('Method not allowed.', 'methodNotAllowed'),
            ];
        }

        $auth = $this->authGuard->validate($headers, $remoteIp);
        $tokenHeaderName = $this->authGuard->getTokenHeaderName();
        $receivedToken = $this->extractHeaderValue($headers, $tokenHeaderName);
        if (empty($auth['ok'])) {
            $this->logger->log([
                'event_id' => '',
                'tipo_evento' => 'webhook_auth_fail',
                'txid' => '',
                'situacao_processamento' => 'erro',
                'payload_raw' => $rawBody,
                'ip_requisicao' => $remoteIp,
                'app_origem' => 'webhook_asaas',
                'header_token_recebido' => $receivedToken,
                'mensagem_erro' => $auth['message'] ?? 'Falha na autenticação.',
                'data_evento_gateway' => null,
            ]);

            return [
                'status_code' => (int) ($auth['statusCode'] ?? 403),
                'payload' => GatewayResponse::error((string) ($auth['message'] ?? 'Access denied.'), (string) ($auth['errorCode'] ?? 'forbidden')),
            ];
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            return [
                'status_code' => 400,
                'payload' => GatewayResponse::error('Invalid payload.', 'invalidPayload'),
            ];
        }

        $eventId = trim((string) (
            $decoded['id']
            ?? $decoded['event']['id']
            ?? $decoded['eventId']
            ?? $decoded['webhook']['id']
            ?? ''
        ));
        $eventType = trim((string) ($decoded['event'] ?? $decoded['type'] ?? 'UNKNOWN'));
        $eventTypeNormalized = strtolower($eventType);
        $paymentNode = $decoded['payment'] ?? [];
        $subscriptionNode = $decoded['subscription'] ?? [];
        $transactionId = trim((string) ($paymentNode['id'] ?? $decoded['paymentId'] ?? $decoded['subscription']['id'] ?? $decoded['id'] ?? ''));
        if ($transactionId === '') {
            $transactionId = trim((string) ($subscriptionNode['id'] ?? ''));
        }

        $statusAsaas = trim((string) ($paymentNode['status'] ?? $subscriptionNode['status'] ?? $decoded['status'] ?? ''));
        $status = $this->statusMapper->toInternalStatus($statusAsaas, $eventTypeNormalized);
        $isLogOnly = in_array($eventTypeNormalized, self::LOG_ONLY_EVENTS, true);

        if ($eventId === '' || $eventType === '') {
            return [
                'status_code' => 400,
                'payload' => GatewayResponse::error('Event without identifier/type.', 'invalidEvent'),
            ];
        }

        $isDuplicate = false;
        if ($this->idempotencyRepository instanceof IdempotencyRepository) {
            try {
                $isDuplicate = !$this->idempotencyRepository->claimEventId($eventId);
            } catch (PDOException $exception) {
                $this->logIdempotencyFailure($eventId, $exception);
            } catch (Throwable $exception) {
                $this->logIdempotencyFailure($eventId, $exception);
            }
        }

        $logBase = [
            'event_id' => $eventId,
            'tipo_evento' => $eventType,
            'txid' => $transactionId,
            'situacao_processamento' => $isDuplicate ? 'ignorado' : ($isLogOnly ? 'log_only' : 'processado'),
            'payload_raw' => $rawBody,
            'ip_requisicao' => $remoteIp,
            'app_origem' => 'webhook_asaas',
            'header_token_recebido' => $receivedToken,
            'mensagem_erro' => '',
            'data_evento_gateway' => date('Y-m-d H:i:s'),
        ];
        $this->logger->log($logBase);

        if ($isDuplicate) {
            return [
                'status_code' => 200,
                'payload' => GatewayResponse::success('Duplicate event ignored.', [
                    'eventId' => $eventId,
                    'transactionId' => $transactionId,
                    'status' => $status,
                ]),
            ];
        }

        if ($isLogOnly) {
            return [
                'status_code' => 200,
                'payload' => GatewayResponse::success('Informational event recorded (log_only).', [
                    'eventId' => $eventId,
                    'eventType' => $eventType,
                    'transactionId' => $transactionId,
                    'status' => $status,
                ]),
            ];
        }

        return [
            'status_code' => 200,
            'payload' => GatewayResponse::success('Event processed successfully.', [
                'eventId' => $eventId,
                'eventType' => $eventType,
                'transactionId' => $transactionId,
                'status' => $status,
                'raw' => $decoded,
            ]),
        ];
    }

    private function logIdempotencyFailure(string $eventId, Throwable $exception): void
    {
        error_log(sprintf(
            '[AsaasWebhookService] Falha ao registrar idempotencia do evento %s: %s',
            $eventId,
            $exception->getMessage()
        ));
    }

    private function extractHeaderValue(array $headers, string $headerName): string
    {
        $needle = strtolower(trim($headerName));
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $needle) {
                continue;
            }

            return trim((string) $value);
        }

        return '';
    }

}
