<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Services;

use AsaasBiblioteca\Config\AsaasConfig;
use AsaasBiblioteca\Http\AsaasHttpClient;
use AsaasBiblioteca\Mappers\CouponToDiscountMapper;
use AsaasBiblioteca\Mappers\AsaasStatusMapper;
use AsaasBiblioteca\Support\FeaturePayloadMerger;

final class AsaasPaymentService
{
    private AsaasHttpClient $client;
    private AsaasStatusMapper $statusMapper;
    private AsaasPixService $pixService;
    private CouponToDiscountMapper $couponMapper;
    private AsaasConfig $config;
    private AsaasCustomerService $customerService;

    public function __construct(
        AsaasHttpClient $client,
        AsaasStatusMapper $statusMapper,
        AsaasPixService $pixService,
        CouponToDiscountMapper $couponMapper,
        AsaasConfig $config,
        AsaasCustomerService $customerService
    )
    {
        $this->client = $client;
        $this->statusMapper = $statusMapper;
        $this->pixService = $pixService;
        $this->couponMapper = $couponMapper;
        $this->config = $config;
        $this->customerService = $customerService;
    }

    public function createPixCharge(array $payload): array
    {
        $normalizedPayload = $this->normalizePaymentPayload($payload, $this->config->getPaymentPixDefaults());
        $normalizedPayload['billingType'] = 'PIX';
        $response = $this->client->post('/payments', $normalizedPayload);
        $data = $response['data'] ?? [];

        $paymentId = (string) ($data['id'] ?? '');
        $pixQr = [];
        if ($paymentId !== '') {
            $pixQr = $this->pixService->getPixQrCodeByPaymentId($paymentId);
        }

        return [
            'success' => true,
            'message' => 'PIX charge created successfully.',
            'data' => [
                'transactionId' => $paymentId,
                'link' => null,
                'pixCode' => $pixQr['data']['pixCode'] ?? null,
                'qrCodeImage' => $pixQr['data']['qrCodeImage'] ?? null,
                'pixKey' => $pixQr['data']['pixKey'] ?? null,
                'expirationDate' => $pixQr['data']['expirationDate'] ?? null,
                'status' => $this->statusMapper->toInternalStatus((string) ($data['status'] ?? '')),
                'raw' => $data,
            ],
        ];
    }

    public function createBilletCharge(array $payload): array
    {
        $normalizedPayload = $this->normalizePaymentPayload($payload, $this->config->getPaymentBilletDefaults());
        $normalizedPayload['billingType'] = 'BOLETO';
        $response = $this->client->post('/payments', $normalizedPayload);
        $data = $response['data'] ?? [];

        return [
            'success' => true,
            'message' => 'Billet charge created successfully.',
            'data' => [
                'transactionId' => $data['id'] ?? null,
                'link' => $data['invoiceUrl'] ?? null,
                'pixCode' => null,
                'status' => $this->statusMapper->toInternalStatus((string) ($data['status'] ?? '')),
                'raw' => $data,
            ],
        ];
    }

    public function createCardPaymentLink(array $payload): array
    {
        $normalizedPayload = $this->normalizePaymentLinkPayload($payload);
        $response = $this->client->post('/paymentLinks', $normalizedPayload);
        $data = $response['data'] ?? [];

        return [
            'success' => true,
            'message' => 'Payment link created successfully.',
            'data' => [
                'transactionId' => $data['id'] ?? null,
                'link' => $data['url'] ?? null,
                'pixCode' => null,
                'status' => 'Pendente',
                'raw' => $data,
            ],
        ];
    }

    public function getPaymentStatus(string $paymentId): array
    {
        $response = $this->client->get('/payments/' . $paymentId);
        $data = $response['data'] ?? [];

        return [
            'success' => true,
            'message' => 'Payment status retrieved successfully.',
            'data' => [
                'transactionId' => $data['id'] ?? $paymentId,
                'link' => $data['invoiceUrl'] ?? null,
                'pixCode' => null,
                'status' => $this->statusMapper->toInternalStatus((string) ($data['status'] ?? '')),
                'raw' => $data,
            ],
        ];
    }

    public function cancelPayment(string $paymentId): array
    {
        $response = $this->client->delete('/payments/' . $paymentId);
        return [
            'success' => true,
            'message' => 'Payment cancelled successfully.',
            'data' => [
                'transactionId' => $paymentId,
                'raw' => $response['data'] ?? [],
            ],
        ];
    }

    public function updatePayment(string $paymentId, array $fields): array
    {
        $paymentId = trim($paymentId);
        if ($paymentId === '') {
            return [
                'success' => false,
                'message' => 'Payment ID is required.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $paymentData = $fields['paymentData'] ?? $fields;
        if (!is_array($paymentData)) {
            $paymentData = [];
        }

        $normalizedPayload = $this->normalizePaymentUpdatePayload($paymentData);
        if ($normalizedPayload === []) {
            return [
                'success' => false,
                'message' => 'Provide at least one field to update the payment.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $response = $this->client->post('/payments/' . $paymentId, $normalizedPayload);
        $data = $response['data'] ?? [];

        return [
            'success' => true,
            'message' => 'Payment updated successfully.',
            'data' => [
                'transactionId' => $data['id'] ?? $paymentId,
                'status' => $this->statusMapper->toInternalStatus((string) ($data['status'] ?? '')),
                'raw' => $data,
            ],
        ];
    }

    public function refundPayment(string $paymentId, array $options = []): array
    {
        $paymentId = trim($paymentId);
        if ($paymentId === '') {
            return [
                'success' => false,
                'message' => 'Payment ID is required.',
                'errorCode' => 'validationError',
                'data' => [],
            ];
        }

        $refundData = $options['refund'] ?? $options;
        if (!is_array($refundData)) {
            $refundData = [];
        }

        $payload = $this->clearNullOrEmpty([
            'value' => isset($refundData['value']) ? (float) $refundData['value'] : null,
            'description' => isset($refundData['description']) ? (string) $refundData['description'] : null,
        ]);

        $response = $this->client->post('/payments/' . $paymentId . '/refund', $payload);
        $data = $response['data'] ?? [];

        return [
            'success' => true,
            'message' => 'Payment refunded successfully.',
            'data' => [
                'transactionId' => $paymentId,
                'refundId' => $data['id'] ?? null,
                'raw' => $data,
            ],
        ];
    }

    private function normalizePaymentPayload(array $payload, array $featureDefaults): array
    {
        $mergedPayload = FeaturePayloadMerger::merge($featureDefaults, $payload);
        $customerId = $this->customerService->resolveCustomerIdForEmission($mergedPayload);
        $dueDate = trim((string) ($mergedPayload['dueDate'] ?? ''));
        if ($dueDate === '') {
            $offsetDays = (int) ($featureDefaults['dueDateOffsetDays'] ?? 0);
            $dueDate = date('Y-m-d', strtotime('+' . $offsetDays . ' day'));
        }

        $normalized = [
            'customer' => $customerId,
            'value' => isset($mergedPayload['value']) ? (float) $mergedPayload['value'] : null,
            'dueDate' => $dueDate,
            'description' => (string) ($mergedPayload['description'] ?? ''),
            'externalReference' => (string) ($mergedPayload['externalReference'] ?? ''),
            'daysAfterDueDateToRegistrationCancellation' => isset($mergedPayload['daysAfterDueDateToRegistrationCancellation'])
                ? (int) $mergedPayload['daysAfterDueDateToRegistrationCancellation']
                : null,
            'postalService' => isset($mergedPayload['postalService']) ? (bool) $mergedPayload['postalService'] : null,
        ];

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

    private function normalizePaymentUpdatePayload(array $payload): array
    {
        $normalized = [
            'billingType' => isset($payload['billingType']) ? (string) $payload['billingType'] : null,
            'value' => isset($payload['value']) ? (float) $payload['value'] : null,
            'dueDate' => isset($payload['dueDate']) ? (string) $payload['dueDate'] : null,
            'description' => isset($payload['description']) ? (string) $payload['description'] : null,
            'externalReference' => isset($payload['externalReference']) ? (string) $payload['externalReference'] : null,
            'daysAfterDueDateToRegistrationCancellation' => isset($payload['daysAfterDueDateToRegistrationCancellation'])
                ? (int) $payload['daysAfterDueDateToRegistrationCancellation']
                : null,
            'postalService' => isset($payload['postalService']) ? (bool) $payload['postalService'] : null,
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

    private function normalizePaymentLinkPayload(array $payload): array
    {
        $featureDefaults = $this->config->getPaymentCardLinkDefaults();
        $mergedPayload = FeaturePayloadMerger::merge($featureDefaults, $payload);
        $normalized = [
            'name' => (string) ($mergedPayload['name'] ?? 'Payment link'),
            'description' => (string) ($mergedPayload['description'] ?? ''),
            'endDate' => (string) ($mergedPayload['endDate'] ?? $this->defaultEndDate()),
            'value' => isset($mergedPayload['value']) ? (float) $mergedPayload['value'] : null,
            'billingType' => (string) ($mergedPayload['billingType'] ?? 'CREDIT_CARD'),
            'chargeType' => (string) ($mergedPayload['chargeType'] ?? 'DETACHED'),
            'dueDateLimitDays' => isset($mergedPayload['dueDateLimitDays']) ? (int) $mergedPayload['dueDateLimitDays'] : null,
            'subscriptionCycle' => (string) ($mergedPayload['subscriptionCycle'] ?? ''),
            'maxInstallmentCount' => isset($mergedPayload['maxInstallmentCount']) ? (int) $mergedPayload['maxInstallmentCount'] : null,
            'externalReference' => (string) ($mergedPayload['externalReference'] ?? ''),
            'notificationEnabled' => isset($mergedPayload['notificationEnabled']) ? (bool) $mergedPayload['notificationEnabled'] : null,
            'isAddressRequired' => isset($mergedPayload['isAddressRequired']) ? (bool) $mergedPayload['isAddressRequired'] : null,
        ];

        if (isset($mergedPayload['callback']) && is_array($mergedPayload['callback'])) {
            $normalized['callback'] = $mergedPayload['callback'];
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

    private function defaultEndDate(): string
    {
        $days = $this->config->getPaymentLinkDefaultEndDateDays();
        return date('Y-m-d', strtotime('+' . $days . ' day'));
    }
}
