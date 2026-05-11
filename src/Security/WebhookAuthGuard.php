<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Security;

use AsaasBiblioteca\Config\AsaasConfig;

final class WebhookAuthGuard
{
    private AsaasConfig $config;

    public function __construct(AsaasConfig $config)
    {
        $this->config = $config;
    }

    public function validate(array $headers, string $remoteIp): array
    {
        if ($this->config->isWebhookIpFilterEnabled()) {
            $allowedIps = $this->config->getWebhookAllowedIps();
            if ($allowedIps !== [] && !in_array($remoteIp, $allowedIps, true)) {
                return [
                    'ok' => false,
                    'statusCode' => 403,
                    'errorCode' => 'ipNotAllowed',
                    'message' => 'Source IP is not allowed.',
                ];
            }
        }

        $tokenHeader = strtolower($this->config->getWebhookTokenHeader());
        $tokenDetection = $this->findTokenFromHeaders($headers, $tokenHeader);
        $receivedToken = $tokenDetection['token'];
        $expectedToken = $this->config->getWebhookToken();
        if ($expectedToken === '') {
            return [
                'ok' => false,
                'statusCode' => 500,
                'errorCode' => 'webhookTokenNotConfigured',
                'message' => 'Webhook token is not configured.',
            ];
        }

        $normalizedExpected = $this->normalizeToken($expectedToken);
        $normalizedReceived = $this->normalizeToken($receivedToken);
        if (!hash_equals($normalizedExpected, $normalizedReceived)) {
            $debugSuffix = '';
            if ($this->config->isDebugEnabled() && $this->config->isDebugSafeDetailsEnabled()) {
                $debugSuffix = sprintf(
                    ' [debug: header_configurado=%s, header_encontrado=%s, token_recebido_len=%d, token_esperado_len=%d, ambiente=%s]',
                    $tokenHeader,
                    $tokenDetection['header'] === '' ? 'nenhum' : $tokenDetection['header'],
                    strlen($normalizedReceived),
                    strlen($normalizedExpected),
                    $this->config->isSandbox() ? 'sandbox' : 'production'
                );
            }
            return [
                'ok' => false,
                'statusCode' => 403,
                'errorCode' => 'invalidToken',
                'message' => 'Invalid webhook token.' . $debugSuffix,
            ];
        }

        return [
            'ok' => true,
            'statusCode' => 200,
            'errorCode' => '',
            'message' => 'Webhook authenticated.',
        ];
    }

    public function getTokenHeaderName(): string
    {
        return $this->config->getWebhookTokenHeader();
    }

    private function findHeader(array $headers, string $needle): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $needle) {
                continue;
            }

            return trim((string) $value);
        }

        return '';
    }

    private function findTokenFromHeaders(array $headers, string $configuredHeader): array
    {
        $candidates = [
            $configuredHeader,
            'x-webhook-token',
            'asaas-access-token',
            'x-asaas-access-token',
            'x-asaas-webhook-token',
        ];

        foreach ($candidates as $candidate) {
            $candidate = strtolower(trim((string) $candidate));
            if ($candidate === '') {
                continue;
            }
            $value = $this->findHeader($headers, $candidate);
            if ($value !== '') {
                return [
                    'token' => $value,
                    'header' => $candidate,
                ];
            }
        }

        return [
            'token' => '',
            'header' => '',
        ];
    }

    private function normalizeToken(string $token): string
    {
        $value = trim($token);
        if (stripos($value, 'bearer ') === 0) {
            $value = trim(substr($value, 7));
        }

        return $value;
    }
}
