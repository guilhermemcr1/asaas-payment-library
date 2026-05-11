<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Services;

use AsaasBiblioteca\Http\AsaasHttpClient;

final class AsaasPixService
{
    private AsaasHttpClient $client;

    public function __construct(AsaasHttpClient $client)
    {
        $this->client = $client;
    }

    public function getPixQrCodeByPaymentId(string $paymentId): array
    {
        $response = $this->client->get('/payments/' . $paymentId . '/pixQrCode');
        $data = $response['data'] ?? [];
        return [
            'success' => true,
            'message' => 'PIX QR code retrieved successfully.',
            'data' => [
                'paymentId' => $paymentId,
                'pixCode' => $this->extractPixCopyPaste(is_array($data) ? $data : []),
                'qrCodeImage' => $data['encodedImage'] ?? null,
                'pixKey' => $this->extractPixKey(is_array($data) ? $data : []),
                'expirationDate' => $data['expirationDate'] ?? null,
                'raw' => $data,
            ],
        ];
    }

    public function extractPixCopyPaste(array $payload): ?string
    {
        $candidate = $payload['payload'] ?? $payload['copyPaste'] ?? null;
        if (!is_string($candidate)) {
            return null;
        }

        $value = trim($candidate);
        if ($value === '') {
            return null;
        }

        return $value;
    }

    public function extractPixKey(array $payload): ?string
    {
        $candidate = $payload['pixKey'] ?? $payload['addressKey'] ?? null;
        if (!is_string($candidate)) {
            return null;
        }

        $value = trim($candidate);
        if ($value === '') {
            return null;
        }

        return $value;
    }
}
