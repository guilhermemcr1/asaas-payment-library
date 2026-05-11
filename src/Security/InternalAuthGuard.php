<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Security;

use AsaasBiblioteca\Config\AsaasConfig;

final class InternalAuthGuard
{
    private AsaasConfig $config;

    public function __construct(AsaasConfig $config)
    {
        $this->config = $config;
    }

    public function validate(array $headers, string $rawBody, string $remoteIp): array
    {
        $allowedIps = $this->config->getInternalAllowedIps();
        if ($allowedIps !== [] && !in_array($remoteIp, $allowedIps, true)) {
            return ['ok' => false, 'statusCode' => 403, 'errorCode' => 'ipNotAllowed', 'message' => 'Internal IP is not allowed.'];
        }

        $token = $this->getHeader($headers, 'x-internal-token');
        $expectedToken = $this->config->getInternalToken();
        if ($expectedToken === '' || !hash_equals($expectedToken, $token)) {
            return ['ok' => false, 'statusCode' => 403, 'errorCode' => 'invalidInternalToken', 'message' => 'Invalid internal token.'];
        }

        $timestamp = $this->getHeader($headers, 'x-timestamp');
        $signature = $this->getHeader($headers, 'x-signature');
        if ($timestamp === '' || $signature === '' || !ctype_digit($timestamp)) {
            return ['ok' => false, 'statusCode' => 403, 'errorCode' => 'invalidSignatureHeaders', 'message' => 'Invalid signature headers.'];
        }

        $timestampInt = (int) $timestamp;
        $window = $this->config->getInternalTimestampWindowSeconds();
        if (abs(time() - $timestampInt) > $window) {
            return ['ok' => false, 'statusCode' => 403, 'errorCode' => 'requestExpired', 'message' => 'Request expired.'];
        }

        $secret = $this->config->getInternalHmacSecret();
        if ($secret === '') {
            return ['ok' => false, 'statusCode' => 500, 'errorCode' => 'internalSecretNotConfigured', 'message' => 'Internal HMAC secret is not configured.'];
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        if (!hash_equals($expectedSignature, $signature)) {
            return ['ok' => false, 'statusCode' => 403, 'errorCode' => 'invalidSignature', 'message' => 'Invalid signature.'];
        }

        return ['ok' => true, 'statusCode' => 200, 'errorCode' => '', 'message' => 'Authenticated.'];
    }

    private function getHeader(array $headers, string $name): string
    {
        $needle = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $needle) {
                continue;
            }
            return trim((string) $value);
        }
        return '';
    }
}
