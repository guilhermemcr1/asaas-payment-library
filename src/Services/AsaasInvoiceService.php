<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Services;

use AsaasBiblioteca\Config\AsaasConfig;
use AsaasBiblioteca\Http\AsaasHttpClient;
use AsaasBiblioteca\Support\PaginationHelper;

final class AsaasInvoiceService
{
    private AsaasHttpClient $client;
    private AsaasConfig $config;

    public function __construct(AsaasHttpClient $client, AsaasConfig $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    public function issueInvoiceForPayment(string $paymentId, array $invoiceData = []): array
    {
        $paymentId = trim($paymentId);
        if ($paymentId === '') {
            return [
                'success' => false,
                'message' => 'paymentId is required to issue an invoice.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $issueNow = array_key_exists('issueNow', $invoiceData)
            ? (bool) $invoiceData['issueNow']
            : $this->config->getInvoiceIssueNowDefault();
        unset($invoiceData['issueNow']);

        $payload = $this->buildInvoicePayload($paymentId, $invoiceData);
        if (!$this->hasMunicipalService($payload)) {
            return [
                'success' => false,
                'message' => 'Provide municipalServiceId or municipalServiceCode in defaults or payload.',
                'errorCode' => 'validationError',
                'data' => ['paymentId' => $paymentId],
            ];
        }

        $response = $this->client->post('/invoices', $payload);
        $scheduledData = $response['data'] ?? [];
        $finalData = $scheduledData;
        $issueResult = null;

        if ($issueNow) {
            $invoiceId = trim((string) ($scheduledData['id'] ?? ''));
            if ($invoiceId === '') {
                return [
                    'success' => false,
                    'message' => 'Invoice was scheduled but no invoiceId was returned to issue now.',
                    'errorCode' => 'invoiceIssueNowMissingId',
                    'data' => [
                        'paymentId' => $paymentId,
                        'scheduledRaw' => $scheduledData,
                    ],
                ];
            }

            $issueResponse = $this->client->post('/invoices/' . $invoiceId . '/authorize');
            $issueResult = $issueResponse['data'] ?? [];
            if (is_array($issueResult) && $issueResult !== []) {
                $finalData = $issueResult;
            }
        }

        return [
            'success' => true,
            'message' => $issueNow ? 'Invoice issued successfully.' : 'Invoice scheduled successfully.',
            'data' => [
                'paymentId' => $paymentId,
                'invoiceId' => $finalData['id'] ?? ($scheduledData['id'] ?? null),
                'status' => $finalData['status'] ?? ($scheduledData['status'] ?? null),
                'raw' => $finalData,
                'scheduledRaw' => $scheduledData,
                'issueRaw' => $issueResult,
            ],
        ];
    }

    public function getInvoice(string $invoiceId): array
    {
        $invoiceId = trim($invoiceId);
        if ($invoiceId === '') {
            return [
                'success' => false,
                'message' => 'Invoice ID is required.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $response = $this->client->get('/invoices/' . $invoiceId);
        $data = $response['data'] ?? [];

        return [
            'success' => true,
            'message' => 'Invoice retrieved successfully.',
            'data' => [
                'invoiceId' => $data['id'] ?? $invoiceId,
                'status' => $data['status'] ?? null,
                'raw' => $data,
            ],
        ];
    }

    public function listInvoices(array $filters = []): array
    {
        $invoiceId = trim((string) ($filters['invoiceId'] ?? ''));
        if ($invoiceId !== '') {
            $single = $this->getInvoice($invoiceId);
            if (empty($single['success'])) {
                return $single;
            }

            return [
                'success' => true,
                'message' => 'Invoice retrieved successfully.',
                'data' => [
                    'item' => $single['data']['raw'] ?? [],
                    'invoiceId' => $invoiceId,
                ],
            ];
        }

        $startDate = trim((string) ($filters['startDate'] ?? ''));
        $endDate = trim((string) ($filters['endDate'] ?? ''));
        if ($startDate === '' || $endDate === '') {
            return [
                'success' => false,
                'message' => 'Provide invoiceId or both startDate and endDate.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $pagination = PaginationHelper::build($filters, $this->config->getListagensDefaultLimit());
        $query = [
            'effectiveDate[ge]' => $startDate,
            'effectiveDate[le]' => $endDate,
            'offset' => $pagination['offset'],
            'limit' => $pagination['limit'],
        ];

        foreach (['payment', 'customer', 'status'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $query[$field] = $value;
            }
        }

        $response = $this->client->get('/invoices', $query);
        $raw = $response['data'] ?? [];
        $list = $raw['data'] ?? [];
        if (!is_array($list)) {
            $list = [];
        }

        $items = array_values(array_filter($list, static fn ($item): bool => is_array($item)));

        return [
            'success' => true,
            'message' => 'Invoices listed successfully.',
            'data' => [
                'items' => $items,
                'pagination' => PaginationHelper::fromRaw($raw, $pagination['offset'], $pagination['limit']),
                'raw' => $raw,
            ],
        ];
    }

    public function cancelInvoice(string $invoiceId): array
    {
        $invoiceId = trim($invoiceId);
        if ($invoiceId === '') {
            return [
                'success' => false,
                'message' => 'Invoice ID is required.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $response = $this->client->post('/invoices/' . $invoiceId . '/cancel');
        $data = $response['data'] ?? [];

        return [
            'success' => true,
            'message' => 'Invoice cancelled successfully.',
            'data' => [
                'invoiceId' => $data['id'] ?? $invoiceId,
                'status' => $data['status'] ?? null,
                'raw' => $data,
            ],
        ];
    }

    private function buildInvoicePayload(string $paymentId, array $invoiceData): array
    {
        $defaults = $this->config->getInvoiceDefaults();
        $payload = array_merge($defaults, $invoiceData);
        $payload['payment'] = $paymentId;

        if (!isset($payload['description']) || trim((string) $payload['description']) === '') {
            $payload['description'] = (string) ($defaults['description'] ?? 'Servico prestado');
        }

        return $this->clearNullOrEmpty($payload);
    }

    private function hasMunicipalService(array $payload): bool
    {
        $id = trim((string) ($payload['municipalServiceId'] ?? ''));
        $code = trim((string) ($payload['municipalServiceCode'] ?? ''));
        return $id !== '' || $code !== '';
    }

    private function clearNullOrEmpty(array $data): array
    {
        $filtered = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_string($value) && trim($value) === '') {
                continue;
            }
            $filtered[$key] = $value;
        }

        return $filtered;
    }
}

