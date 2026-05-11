<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Http;

use AsaasBiblioteca\Config\AsaasConfig;
use AsaasBiblioteca\Exceptions\AsaasException;
use AsaasBiblioteca\Exceptions\AsaasHttpException;

final class AsaasHttpClient
{
    private AsaasConfig $config;

    public function __construct(AsaasConfig $config)
    {
        $this->config = $config;
    }

    public function get(string $path, array $query = []): array
    {
        $url = $this->buildUrl($path, $query);
        return $this->request('GET', $url);
    }

    public function post(string $path, array $payload = []): array
    {
        $url = $this->buildUrl($path);
        return $this->request('POST', $url, $payload);
    }

    public function delete(string $path): array
    {
        $url = $this->buildUrl($path);
        return $this->request('DELETE', $url);
    }

    private function buildUrl(string $path, array $query = []): string
    {
        $baseUrl = $this->config->getApiBaseUrl();
        $normalizedPath = '/' . ltrim($path, '/');
        $url = $baseUrl . $normalizedPath;
        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query);
    }

    private function request(string $method, string $url, array $payload = []): array
    {
        $apiKey = $this->config->getApiKey();
        if ($apiKey === '') {
            throw new AsaasException('ASAAS_API_KEY não configurada.');
        }

        $attempts = 0;
        $maxAttempts = 1 + $this->config->getRetryAttempts();
        $lastError = null;

        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                return $this->performRequest($method, $url, $apiKey, $payload);
            } catch (AsaasHttpException $e) {
                $lastError = $e;
                $isRetriable = $e->getStatusCode() >= 500 || $e->getStatusCode() === 429;
                if (!$isRetriable || $attempts >= $maxAttempts) {
                    throw $e;
                }
                usleep(200000);
            }
        }

        if ($lastError instanceof AsaasHttpException) {
            throw $lastError;
        }

        throw new AsaasException('Falha inesperada na comunicação HTTP com Asaas.');
    }

    private function performRequest(string $method, string $url, string $apiKey, array $payload): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new AsaasException('Não foi possível inicializar cURL.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'access_token: ' . $apiKey,
            'User-Agent: ' . $this->config->getApiUserAgent(),
        ];

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->config->getTimeoutSeconds());

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $rawResponse = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($rawResponse === false) {
            throw new AsaasException('Erro de rede na comunicação com Asaas: ' . $curlError);
        }

        $decoded = json_decode((string) $rawResponse, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => (string) $rawResponse];
        }

        if ($statusCode >= 200 && $statusCode < 300) {
            return [
                'success' => true,
                'status_code' => $statusCode,
                'data' => $decoded,
            ];
        }

        $message = trim((string) ($decoded['message'] ?? ''));
        if ($message === '' && isset($decoded['errors']) && is_array($decoded['errors'])) {
            $first = $decoded['errors'][0] ?? [];
            if (is_array($first)) {
                $message = trim((string) ($first['description'] ?? $first['message'] ?? ''));
            }
        }
        if ($message === '') {
            $message = 'Erro na API Asaas.';
        }

        throw new AsaasHttpException($message, $statusCode, $decoded);
    }
}
