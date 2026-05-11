<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Services;

use AsaasBiblioteca\Config\AsaasConfig;
use AsaasBiblioteca\Http\AsaasHttpClient;
use AsaasBiblioteca\Mappers\AsaasStatusMapper;
use AsaasBiblioteca\Mappers\CouponToDiscountMapper;
use AsaasBiblioteca\Support\FeaturePayloadMerger;
use AsaasBiblioteca\Support\PaginationHelper;

final class AsaasSubscriptionService
{
    private AsaasHttpClient $client;
    private AsaasStatusMapper $statusMapper;
    private CouponToDiscountMapper $couponMapper;
    private AsaasCustomerService $customerService;
    private AsaasConfig $config;

    public function __construct(
        AsaasHttpClient $client,
        AsaasStatusMapper $statusMapper,
        CouponToDiscountMapper $couponMapper,
        AsaasCustomerService $customerService,
        AsaasConfig $config
    )
    {
        $this->client = $client;
        $this->statusMapper = $statusMapper;
        $this->couponMapper = $couponMapper;
        $this->customerService = $customerService;
        $this->config = $config;
    }

    public function createSubscription(array $payload): array
    {
        $normalizedPayload = $this->normalizeSubscriptionPayload($payload);
        $response = $this->client->post('/subscriptions', $normalizedPayload);
        $data = $response['data'] ?? [];
        $subscriptionId = (string) ($data['id'] ?? '');
        $invoiceUrl = (string) ($data['invoiceUrl'] ?? '');
        if ($invoiceUrl === '' && $subscriptionId !== '') {
            $invoiceUrl = $this->resolveSubscriptionInvoiceUrl($subscriptionId);
        }

        return [
            'success' => true,
            'message' => 'Subscription created successfully.',
            'data' => [
                'transactionId' => $subscriptionId !== '' ? $subscriptionId : null,
                'link' => $invoiceUrl !== '' ? $invoiceUrl : null,
                'pixCode' => null,
                'status' => $this->statusMapper->toInternalStatus((string) ($data['status'] ?? '')),
                'raw' => $data,
            ],
        ];
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        $response = $this->client->delete('/subscriptions/' . $subscriptionId);
        return [
            'success' => true,
            'message' => 'Subscription cancelled successfully.',
            'data' => [
                'transactionId' => $subscriptionId,
                'raw' => $response['data'] ?? [],
            ],
        ];
    }

    public function getSubscriptionPayments(string $subscriptionId, array $filters = []): array
    {
        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            return [
                'success' => false,
                'message' => 'Subscription ID is required.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $pagination = PaginationHelper::build($filters, $this->config->getListagensDefaultLimit());
        $query = [
            'subscription' => $subscriptionId,
            'offset' => $pagination['offset'],
            'limit' => $pagination['limit'],
        ];

        foreach (['status', 'billingType'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $query[$field] = $value;
            }
        }

        $response = $this->client->get('/payments', $query);
        $raw = $response['data'] ?? [];
        $list = $raw['data'] ?? [];
        if (!is_array($list)) {
            $list = [];
        }

        $items = [];
        foreach ($list as $payment) {
            if (!is_array($payment)) {
                continue;
            }
            $items[] = [
                'transactionId' => $payment['id'] ?? null,
                'link' => $payment['invoiceUrl'] ?? null,
                'status' => $this->statusMapper->toInternalStatus((string) ($payment['status'] ?? '')),
                'raw' => $payment,
            ];
        }

        return [
            'success' => true,
            'message' => 'Subscription payments listed successfully.',
            'data' => [
                'subscriptionId' => $subscriptionId,
                'items' => $items,
                'pagination' => PaginationHelper::fromRaw($raw, $pagination['offset'], $pagination['limit']),
                'raw' => $raw,
            ],
        ];
    }

    public function updateSubscription(string $subscriptionId, array $fields): array
    {
        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            return [
                'success' => false,
                'message' => 'Subscription ID is required.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $subscriptionData = $fields['subscriptionData'] ?? $fields;
        if (!is_array($subscriptionData)) {
            $subscriptionData = [];
        }

        $normalizedPayload = $this->normalizeSubscriptionUpdatePayload($subscriptionData);
        if ($normalizedPayload === []) {
            return [
                'success' => false,
                'message' => 'Provide at least one field to update the subscription.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $response = $this->client->post('/subscriptions/' . $subscriptionId, $normalizedPayload);
        $data = $response['data'] ?? [];

        return [
            'success' => true,
            'message' => 'Subscription updated successfully.',
            'data' => [
                'transactionId' => $data['id'] ?? $subscriptionId,
                'link' => $data['invoiceUrl'] ?? null,
                'status' => $this->statusMapper->toInternalStatus((string) ($data['status'] ?? '')),
                'raw' => $data,
            ],
        ];
    }

    private function normalizeSubscriptionPayload(array $payload): array
    {
        $featureDefaults = $this->config->getSubscriptionDefaults();
        $mergedPayload = FeaturePayloadMerger::merge($featureDefaults, $payload);
        $customerId = $this->customerService->resolveCustomerIdForEmission($mergedPayload);
        $nextDueDate = trim((string) ($mergedPayload['nextDueDate'] ?? ''));
        if ($nextDueDate === '') {
            $offsetDays = (int) ($featureDefaults['nextDueDateOffsetDays'] ?? 0);
            $nextDueDate = date('Y-m-d', strtotime('+' . $offsetDays . ' day'));
        }

        $normalized = [
            'customer' => $customerId,
            'billingType' => (string) ($mergedPayload['billingType'] ?? 'CREDIT_CARD'),
            'value' => isset($mergedPayload['value']) ? (float) $mergedPayload['value'] : null,
            'nextDueDate' => $nextDueDate,
            'cycle' => (string) ($mergedPayload['cycle'] ?? 'MONTHLY'),
            'description' => (string) ($mergedPayload['description'] ?? ''),
            'endDate' => (string) ($mergedPayload['endDate'] ?? ''),
            'maxPayments' => isset($mergedPayload['maxPayments']) ? (int) $mergedPayload['maxPayments'] : null,
            'externalReference' => (string) ($mergedPayload['externalReference'] ?? ''),
        ];

        if (isset($mergedPayload['updatePendingPayments'])) {
            $normalized['updatePendingPayments'] = (bool) $mergedPayload['updatePendingPayments'];
        }

        $discount = $this->couponMapper->map($mergedPayload);
        if ($discount !== null) {
            $normalized['discount'] = $discount;
        }

        if (isset($mergedPayload['discount']) && is_array($mergedPayload['discount'])) {
            $normalized['discount'] = $mergedPayload['discount'];
        }

        if (isset($mergedPayload['fine']) && is_array($mergedPayload['fine'])) {
            $normalized['fine'] = $mergedPayload['fine'];
        }
        if (isset($mergedPayload['interest']) && is_array($mergedPayload['interest'])) {
            $normalized['interest'] = $mergedPayload['interest'];
        }
        if (isset($mergedPayload['split']) && is_array($mergedPayload['split'])) {
            $normalized['split'] = $mergedPayload['split'];
        }
        if (isset($mergedPayload['callback']) && is_array($mergedPayload['callback'])) {
            $normalized['callback'] = $mergedPayload['callback'];
        }

        return $this->clearNullOrEmpty($normalized);
    }

    private function normalizeSubscriptionUpdatePayload(array $payload): array
    {
        $normalized = [
            'customer' => isset($payload['customer']) ? trim((string) $payload['customer']) : null,
            'billingType' => isset($payload['billingType']) ? (string) $payload['billingType'] : null,
            'value' => isset($payload['value']) ? (float) $payload['value'] : null,
            'nextDueDate' => isset($payload['nextDueDate']) ? (string) $payload['nextDueDate'] : null,
            'cycle' => isset($payload['cycle']) ? (string) $payload['cycle'] : null,
            'description' => isset($payload['description']) ? (string) $payload['description'] : null,
            'endDate' => isset($payload['endDate']) ? (string) $payload['endDate'] : null,
            'maxPayments' => isset($payload['maxPayments']) ? (int) $payload['maxPayments'] : null,
            'externalReference' => isset($payload['externalReference']) ? (string) $payload['externalReference'] : null,
            'updatePendingPayments' => isset($payload['updatePendingPayments']) ? (bool) $payload['updatePendingPayments'] : null,
        ];

        if (isset($payload['discount']) && is_array($payload['discount'])) {
            $normalized['discount'] = $payload['discount'];
        }
        if (isset($payload['fine']) && is_array($payload['fine'])) {
            $normalized['fine'] = $payload['fine'];
        }
        if (isset($payload['interest']) && is_array($payload['interest'])) {
            $normalized['interest'] = $payload['interest'];
        }
        if (isset($payload['split']) && is_array($payload['split'])) {
            $normalized['split'] = $payload['split'];
        }
        if (isset($payload['callback']) && is_array($payload['callback'])) {
            $normalized['callback'] = $payload['callback'];
        }

        return $this->clearNullOrEmpty($normalized);
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

    private function resolveSubscriptionInvoiceUrl(string $subscriptionId): string
    {
        $attempts = 3;
        $waitMicroseconds = 300000;

        for ($i = 0; $i < $attempts; $i++) {
            $response = $this->client->get('/payments', [
                'subscription' => $subscriptionId,
                'limit' => 1,
                'offset' => 0,
            ]);
            $data = $response['data'] ?? [];
            $list = $data['data'] ?? [];
            if (is_array($list) && $list !== []) {
                $firstPayment = $list[0] ?? [];
                if (is_array($firstPayment)) {
                    $invoiceUrl = trim((string) ($firstPayment['invoiceUrl'] ?? ''));
                    if ($invoiceUrl !== '') {
                        return $invoiceUrl;
                    }
                }
            }

            if ($i < ($attempts - 1)) {
                usleep($waitMicroseconds);
            }
        }

        return '';
    }
}
