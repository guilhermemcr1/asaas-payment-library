<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Http;

use AsaasBiblioteca\Config\AsaasConfig;
use AsaasBiblioteca\DTO\GatewayResponse;

final class ActionContractValidator
{
    public static function validate(string $action, array $payload, AsaasConfig $config): ?array
    {
        switch ($action) {
            case 'create_payment':
                return self::validateCreatePayment($payload, $config);
            case 'cancel_payment':
            case 'update_payment':
            case 'refund_payment':
            case 'get_payment_status':
            case 'get_pix_qrcode':
                return self::requireNonEmptyField($payload, 'paymentId', 'paymentId is required.');
            case 'cancel_subscription':
            case 'get_subscription_payments':
            case 'update_subscription':
                return self::requireNonEmptyField($payload, 'subscriptionId', 'subscriptionId is required.');
            case 'get_customer':
            case 'delete_customer':
                return self::requireAnyNonEmptyField($payload, ['customerId', 'customer'], 'customerId or customer is required.');
            case 'update_customer':
                return self::validateUpdateCustomer($payload);
            case 'issue_invoice':
                return self::requireNonEmptyField($payload, 'paymentId', 'paymentId is required.');
            case 'get_invoice':
            case 'cancel_invoice':
                return self::requireNonEmptyField($payload, 'invoiceId', 'invoiceId is required.');
            default:
                return null;
        }
    }

    private static function validateCreatePayment(array $payload, AsaasConfig $config): ?array
    {
        $paymentMethod = strtolower(trim((string) ($payload['paymentMethod'] ?? $config->getDefaultPaymentMethod())));
        $allowedPaymentMethods = ['pix', 'boleto', 'cartao'];
        if (in_array($paymentMethod, $allowedPaymentMethods, true)) {
            return null;
        }

        return GatewayResponse::error(
            'Invalid paymentMethod. Allowed values: pix, boleto, cartao.',
            'validationError'
        );
    }

    private static function validateUpdateCustomer(array $payload): ?array
    {
        $customerError = self::requireAnyNonEmptyField($payload, ['customer', 'customerId'], 'customer or customerId is required.');
        if ($customerError !== null) {
            return $customerError;
        }

        $customerData = $payload['customerData'] ?? null;
        if (!is_array($customerData) || $customerData === []) {
            return GatewayResponse::error('customerData is required.', 'validationError');
        }

        return null;
    }

    private static function requireNonEmptyField(array $payload, string $field, string $message): ?array
    {
        if (self::hasNonEmptyValue($payload, $field)) {
            return null;
        }

        return GatewayResponse::error($message, 'validationError');
    }

    private static function requireAnyNonEmptyField(array $payload, array $fields, string $message): ?array
    {
        foreach ($fields as $field) {
            if (self::hasNonEmptyValue($payload, $field)) {
                return null;
            }
        }

        return GatewayResponse::error($message, 'validationError');
    }

    private static function hasNonEmptyValue(array $payload, string $field): bool
    {
        if (!array_key_exists($field, $payload)) {
            return false;
        }

        return trim((string) $payload[$field]) !== '';
    }
}
