<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Http;

use AsaasBiblioteca\Config\AsaasConfig;
use AsaasBiblioteca\Contracts\PaymentGatewayInterface;
use AsaasBiblioteca\DTO\GatewayResponse;

final class ActionRouter
{
    private PaymentGatewayInterface $gateway;
    private AsaasConfig $config;

    public function __construct(PaymentGatewayInterface $gateway, ?AsaasConfig $config = null)
    {
        $this->gateway = $gateway;
        $this->config = $config ?? new AsaasConfig();
    }

    public function handle(string $action, array $payload, array $headers, string $remoteIp, string $rawBody, string $httpMethod): array
    {
        $contractError = ActionContractValidator::validate($action, $payload, $this->config);
        if ($contractError !== null) {
            return [
                'status_code' => 400,
                'payload' => $contractError,
            ];
        }

        switch ($action) {
            case 'create_payment':
                $paymentMethod = strtolower(trim((string) ($payload['paymentMethod'] ?? $this->config->getDefaultPaymentMethod())));
                if ($paymentMethod === 'pix') {
                    return ['status_code' => 200, 'payload' => $this->gateway->createPixCharge($payload)];
                }
                if ($paymentMethod === 'boleto') {
                    return ['status_code' => 200, 'payload' => $this->gateway->createBilletCharge($payload)];
                }
                return ['status_code' => 200, 'payload' => $this->gateway->createCardPaymentLink($payload)];
            case 'create_subscription':
                return ['status_code' => 200, 'payload' => $this->gateway->createSubscription($payload)];
            case 'cancel_subscription':
                return ['status_code' => 200, 'payload' => $this->gateway->cancelSubscription((string) ($payload['subscriptionId'] ?? ''))];
            case 'get_subscription_payments':
                return [
                    'status_code' => 200,
                    'payload' => $this->gateway->getSubscriptionPayments(
                        (string) ($payload['subscriptionId'] ?? ''),
                        (array) ($payload['filters'] ?? $payload)
                    ),
                ];
            case 'update_subscription':
                return [
                    'status_code' => 200,
                    'payload' => $this->gateway->updateSubscription(
                        (string) ($payload['subscriptionId'] ?? ''),
                        (array) ($payload['subscriptionData'] ?? $payload)
                    ),
                ];
            case 'cancel_payment':
                return ['status_code' => 200, 'payload' => $this->gateway->cancelPayment((string) ($payload['paymentId'] ?? ''))];
            case 'update_payment':
                return [
                    'status_code' => 200,
                    'payload' => $this->gateway->updatePayment(
                        (string) ($payload['paymentId'] ?? ''),
                        (array) ($payload['paymentData'] ?? $payload)
                    ),
                ];
            case 'refund_payment':
                return [
                    'status_code' => 200,
                    'payload' => $this->gateway->refundPayment(
                        (string) ($payload['paymentId'] ?? ''),
                        (array) ($payload['refund'] ?? $payload)
                    ),
                ];
            case 'get_payment_status':
                return ['status_code' => 200, 'payload' => $this->gateway->getPaymentStatus((string) ($payload['paymentId'] ?? ''))];
            case 'get_pix_qrcode':
                return ['status_code' => 200, 'payload' => $this->gateway->getPixQrCodeByPaymentId((string) ($payload['paymentId'] ?? ''))];
            case 'create_customer':
                return ['status_code' => 200, 'payload' => $this->gateway->createCustomer($payload)];
            case 'get_customer':
                return ['status_code' => 200, 'payload' => $this->gateway->getCustomer((string) ($payload['customerId'] ?? $payload['customer'] ?? ''))];
            case 'list_customers':
                return ['status_code' => 200, 'payload' => $this->gateway->listCustomers((array) ($payload['filters'] ?? $payload))];
            case 'delete_customer':
                return ['status_code' => 200, 'payload' => $this->gateway->deleteCustomer((string) ($payload['customerId'] ?? $payload['customer'] ?? ''))];
            case 'update_customer':
                return [
                    'status_code' => 200,
                    'payload' => $this->gateway->updateCustomerData(
                        (string) ($payload['customer'] ?? $payload['customerId'] ?? ''),
                        (array) ($payload['customerData'] ?? [])
                    ),
                ];
            case 'issue_invoice':
                $invoicePayload = (array) ($payload['invoice'] ?? []);
                $description = trim((string) ($payload['description'] ?? $payload['invoiceDescription'] ?? ''));
                if ($description !== '' && !isset($invoicePayload['description'])) {
                    $invoicePayload['description'] = $description;
                }
                if (array_key_exists('issueNow', $payload) && !isset($invoicePayload['issueNow'])) {
                    $invoicePayload['issueNow'] = (bool) $payload['issueNow'];
                }

                return [
                    'status_code' => 200,
                    'payload' => $this->gateway->issueInvoice(
                        (string) ($payload['paymentId'] ?? ''),
                        $invoicePayload
                    ),
                ];
            case 'get_invoice':
                return ['status_code' => 200, 'payload' => $this->gateway->getInvoice((string) ($payload['invoiceId'] ?? ''))];
            case 'list_invoices':
                return ['status_code' => 200, 'payload' => $this->gateway->listInvoices((array) ($payload['filters'] ?? $payload))];
            case 'cancel_invoice':
                return ['status_code' => 200, 'payload' => $this->gateway->cancelInvoice((string) ($payload['invoiceId'] ?? ''))];
            case 'webhook_receive':
                return $this->gateway->processWebhook($rawBody, $headers, $remoteIp, $httpMethod);
            default:
                return [
                    'status_code' => 400,
                    'payload' => GatewayResponse::error('Invalid action.', 'invalid_action'),
                ];
        }
    }
}
