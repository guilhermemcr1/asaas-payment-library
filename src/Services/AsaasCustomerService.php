<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Services;

use AsaasBiblioteca\Config\AsaasConfig;
use AsaasBiblioteca\Http\AsaasHttpClient;
use AsaasBiblioteca\Support\PaginationHelper;

final class AsaasCustomerService
{
    private AsaasHttpClient $client;
    private AsaasConfig $config;

    public function __construct(AsaasHttpClient $client, AsaasConfig $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    public function resolveCustomerIdForEmission(array $payload): string
    {
        $customerId = trim((string) ($payload['customer'] ?? ''));
        $customerData = $this->extractCustomerData($payload);

        if ($customerId !== '') {
            if ($customerData !== []) {
                $existing = $this->getCustomerById($customerId);
                if ($existing !== null && $this->hasCustomerDifference($existing, $customerData)) {
                    $this->updateCustomer($customerId, $customerData);
                }
            }
            return $customerId;
        }

        if ($customerData === []) {
            return '';
        }

        $existing = $this->findExistingCustomer($customerData);
        if ($existing !== null) {
            $existingId = trim((string) ($existing['id'] ?? ''));
            if ($existingId !== '' && $this->hasCustomerDifference($existing, $customerData)) {
                $this->updateCustomer($existingId, $customerData);
            }
            return $existingId;
        }

        $created = $this->createCustomerRecord($customerData);
        return trim((string) ($created['id'] ?? ''));
    }

    public function createCustomer(array $payload): array
    {
        $customerData = $this->extractCustomerData($payload);
        if ($customerData === []) {
            return [
                'success' => false,
                'message' => 'Provide customerData or customer fields to create a customer.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $created = $this->createCustomerRecord($customerData);
        $customerId = trim((string) ($created['id'] ?? ''));
        if ($customerId === '') {
            return [
                'success' => false,
                'message' => 'Customer was not created.',
                'errorCode' => 'customerCreateFailed',
                'data' => ['raw' => $created],
            ];
        }

        return [
            'success' => true,
            'message' => 'Customer created successfully.',
            'data' => [
                'customer' => $customerId,
                'raw' => $created,
            ],
        ];
    }

    public function getCustomer(string $customerId): array
    {
        $customerId = trim($customerId);
        if ($customerId === '') {
            return [
                'success' => false,
                'message' => 'Customer ID is required.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $customer = $this->getCustomerById($customerId);
        if ($customer === null) {
            return [
                'success' => false,
                'message' => 'Customer not found.',
                'errorCode' => 'customerNotFound',
                'data' => ['customer' => $customerId],
            ];
        }

        return [
            'success' => true,
            'message' => 'Customer retrieved successfully.',
            'data' => [
                'customer' => $customerId,
                'raw' => $customer,
            ],
        ];
    }

    public function listCustomers(array $filters = []): array
    {
        $customerId = trim((string) ($filters['customerId'] ?? ''));
        if ($customerId !== '') {
            $single = $this->getCustomer($customerId);
            if (empty($single['success'])) {
                return $single;
            }

            return [
                'success' => true,
                'message' => 'Customer retrieved successfully.',
                'data' => [
                    'item' => $single['data']['raw'] ?? [],
                    'customer' => $customerId,
                ],
            ];
        }

        $startDate = trim((string) ($filters['startDate'] ?? ''));
        $endDate = trim((string) ($filters['endDate'] ?? ''));
        if ($startDate === '' || $endDate === '') {
            return [
                'success' => false,
                'message' => 'Provide customerId or both startDate and endDate.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $pagination = PaginationHelper::build($filters, $this->config->getListagensDefaultLimit());
        $query = [
            'dateCreated[ge]' => $startDate,
            'dateCreated[le]' => $endDate,
            'offset' => $pagination['offset'],
            'limit' => $pagination['limit'],
        ];

        foreach (['name', 'email', 'cpfCnpj', 'externalReference'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $query[$field] = $value;
            }
        }

        $response = $this->client->get('/customers', $query);
        $raw = $response['data'] ?? [];
        $items = $this->extractCustomerList($raw);

        return [
            'success' => true,
            'message' => 'Customers listed successfully.',
            'data' => [
                'items' => $items,
                'pagination' => PaginationHelper::fromRaw($raw, $pagination['offset'], $pagination['limit']),
                'raw' => $raw,
            ],
        ];
    }

    public function deleteCustomer(string $customerId): array
    {
        $customerId = trim($customerId);
        if ($customerId === '') {
            return [
                'success' => false,
                'message' => 'Customer ID is required.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $response = $this->client->delete('/customers/' . $customerId);

        return [
            'success' => true,
            'message' => 'Customer deleted successfully.',
            'data' => [
                'customer' => $customerId,
                'raw' => $response['data'] ?? [],
            ],
        ];
    }

    public function updateCustomerData(string $customerId, array $fields): array
    {
        $customerId = trim($customerId);
        if ($customerId === '') {
            return [
                'success' => false,
                'message' => 'Customer ID is required.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $customerData = $this->extractCustomerData(['customerData' => $fields]);
        if ($customerData === []) {
            return [
                'success' => false,
                'message' => 'Provide at least one field to update the customer.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $this->updateCustomer($customerId, $customerData);
        $updatedCustomer = $this->getCustomerById($customerId);

        return [
            'success' => true,
            'message' => 'Customer updated successfully.',
            'data' => [
                'customer' => $customerId,
                'updatedFields' => array_keys($customerData),
                'raw' => $updatedCustomer ?? [],
            ],
        ];
    }

    private function extractCustomerData(array $payload): array
    {
        $customerData = $payload['customerData'] ?? [];
        if (!is_array($customerData)) {
            $customerData = [];
        }

        $fallbackMap = [
            'name',
            'cpfCnpj',
            'email',
            'phone',
            'mobilePhone',
            'postalCode',
            'address',
            'addressNumber',
            'complement',
            'province',
            'externalReference',
            'company',
            'notificationDisabled',
            'additionalEmails',
            'municipalInscription',
            'stateInscription',
            'observations',
            'groupName',
        ];

        foreach ($fallbackMap as $field) {
            if (array_key_exists($field, $payload) && !array_key_exists($field, $customerData)) {
                $customerData[$field] = $payload[$field];
            }
        }

        $normalized = [];
        foreach ($customerData as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if ($value === null) {
                continue;
            }
            if (is_string($value) && trim($value) === '') {
                continue;
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function findExistingCustomer(array $customerData): ?array
    {
        $queryCandidates = [
            'cpfCnpj' => $customerData['cpfCnpj'] ?? null,
            'externalReference' => $customerData['externalReference'] ?? null,
            'email' => $customerData['email'] ?? null,
        ];

        foreach ($queryCandidates as $field => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $response = $this->client->get('/customers', [$field => $value]);
            $list = $this->extractCustomerList($response['data'] ?? []);
            if ($list !== []) {
                return $list[0];
            }
        }

        return null;
    }

    private function createCustomerRecord(array $customerData): array
    {
        $payload = $this->clearNullOrEmpty($customerData);
        if (!isset($payload['name'])) {
            $resolutionDefaults = $this->config->getCustomerResolutionDefaults();
            $payload['name'] = (string) ($resolutionDefaults['fallbackName'] ?? 'Default Customer');
        }

        $response = $this->client->post('/customers', $payload);
        $data = $response['data'] ?? [];
        if (isset($data['id'])) {
            return $data;
        }

        return [];
    }

    private function updateCustomer(string $customerId, array $customerData): void
    {
        $payload = $this->clearNullOrEmpty($customerData);
        if ($payload === []) {
            return;
        }
        $this->client->post('/customers/' . $customerId, $payload);
    }

    private function getCustomerById(string $customerId): ?array
    {
        $response = $this->client->get('/customers/' . $customerId);
        $data = $response['data'] ?? [];
        if (!is_array($data) || $data === []) {
            return null;
        }

        return $data;
    }

    private function extractCustomerList(array $rawData): array
    {
        $list = $rawData['data'] ?? [];
        if (!is_array($list)) {
            return [];
        }

        return array_values(array_filter($list, static fn ($item): bool => is_array($item)));
    }

    private function hasCustomerDifference(array $existing, array $incoming): bool
    {
        $fields = [
            'name',
            'cpfCnpj',
            'email',
            'phone',
            'mobilePhone',
            'postalCode',
            'address',
            'addressNumber',
            'complement',
            'province',
            'externalReference',
        ];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $incoming)) {
                continue;
            }
            $left = $this->normalizeField($field, $existing[$field] ?? null);
            $right = $this->normalizeField($field, $incoming[$field] ?? null);
            if ($left !== $right) {
                return true;
            }
        }

        return false;
    }

    private function normalizeField(string $field, $value): string
    {
        $value = trim((string) $value);
        if (in_array($field, ['cpfCnpj', 'phone', 'mobilePhone', 'postalCode'], true)) {
            return preg_replace('/\D+/', '', $value) ?? '';
        }

        return mb_strtolower($value, 'UTF-8');
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
