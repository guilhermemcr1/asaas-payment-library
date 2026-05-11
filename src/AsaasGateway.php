<?php

declare(strict_types=1);

namespace AsaasBiblioteca;

use AsaasBiblioteca\Config\AsaasConfig;
use AsaasBiblioteca\Contracts\PaymentGatewayInterface;
use AsaasBiblioteca\Http\AsaasHttpClient;
use AsaasBiblioteca\Infrastructure\EventLogRepository;
use AsaasBiblioteca\Infrastructure\IdempotencyRepository;
use AsaasBiblioteca\Infrastructure\PdoConnectionFactory;
use AsaasBiblioteca\Mappers\CouponToDiscountMapper;
use AsaasBiblioteca\Mappers\AsaasStatusMapper;
use AsaasBiblioteca\Security\WebhookAuthGuard;
use AsaasBiblioteca\Audit\AsaasEventLogger;
use AsaasBiblioteca\Services\AsaasPaymentService;
use AsaasBiblioteca\Services\AsaasPixService;
use AsaasBiblioteca\Services\AsaasSubscriptionService;
use AsaasBiblioteca\Services\AsaasCustomerService;
use AsaasBiblioteca\Services\AsaasInvoiceService;
use AsaasBiblioteca\Services\AsaasWebhookService;

final class AsaasGateway implements PaymentGatewayInterface
{
    private AsaasPaymentService $paymentService;
    private AsaasSubscriptionService $subscriptionService;
    private AsaasPixService $pixService;
    private AsaasWebhookService $webhookService;
    private AsaasCustomerService $customerService;
    private AsaasInvoiceService $invoiceService;

    public function __construct(array $config = [])
    {
        $asaasConfig = new AsaasConfig($config);
        $client = new AsaasHttpClient($asaasConfig);
        $mapper = new AsaasStatusMapper();
        $couponMapper = new CouponToDiscountMapper();
        $pdo = (new PdoConnectionFactory($asaasConfig))->getConnection();
        $eventLogRepository = new EventLogRepository($pdo);
        $idempotencyRepository = new IdempotencyRepository($pdo);
        $pixService = new AsaasPixService($client);
        $customerService = new AsaasCustomerService($client, $asaasConfig);
        $paymentService = new AsaasPaymentService($client, $mapper, $pixService, $couponMapper, $asaasConfig, $customerService);
        $subscriptionService = new AsaasSubscriptionService($client, $mapper, $couponMapper, $customerService, $asaasConfig);
        $invoiceService = new AsaasInvoiceService($client, $asaasConfig);
        $webhookService = new AsaasWebhookService(
            new WebhookAuthGuard($asaasConfig),
            new AsaasEventLogger($eventLogRepository),
            $mapper,
            $idempotencyRepository
        );

        $this->pixService = $pixService;
        $this->paymentService = $paymentService;
        $this->subscriptionService = $subscriptionService;
        $this->webhookService = $webhookService;
        $this->customerService = $customerService;
        $this->invoiceService = $invoiceService;
    }

    public function createPixCharge(array $payload): array
    {
        return $this->paymentService->createPixCharge($payload);
    }

    public function createBilletCharge(array $payload): array
    {
        return $this->paymentService->createBilletCharge($payload);
    }

    public function createCardPaymentLink(array $payload): array
    {
        return $this->paymentService->createCardPaymentLink($payload);
    }

    public function createSubscription(array $payload): array
    {
        return $this->subscriptionService->createSubscription($payload);
    }

    public function getPaymentStatus(string $paymentId): array
    {
        return $this->paymentService->getPaymentStatus($paymentId);
    }

    public function getPixQrCodeByPaymentId(string $paymentId): array
    {
        return $this->pixService->getPixQrCodeByPaymentId($paymentId);
    }

    public function extractPixCopyPaste(array $payload): ?string
    {
        return $this->pixService->extractPixCopyPaste($payload);
    }

    public function extractPixKey(array $payload): ?string
    {
        return $this->pixService->extractPixKey($payload);
    }

    public function cancelPayment(string $paymentId): array
    {
        return $this->paymentService->cancelPayment($paymentId);
    }

    public function updatePayment(string $paymentId, array $fields): array
    {
        return $this->paymentService->updatePayment($paymentId, $fields);
    }

    public function refundPayment(string $paymentId, array $options = []): array
    {
        return $this->paymentService->refundPayment($paymentId, $options);
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->subscriptionService->cancelSubscription($subscriptionId);
    }

    public function getSubscriptionPayments(string $subscriptionId, array $filters = []): array
    {
        return $this->subscriptionService->getSubscriptionPayments($subscriptionId, $filters);
    }

    public function updateSubscription(string $subscriptionId, array $fields): array
    {
        return $this->subscriptionService->updateSubscription($subscriptionId, $fields);
    }

    public function createCustomer(array $payload): array
    {
        return $this->customerService->createCustomer($payload);
    }

    public function getCustomer(string $customerId): array
    {
        return $this->customerService->getCustomer($customerId);
    }

    public function listCustomers(array $filters = []): array
    {
        return $this->customerService->listCustomers($filters);
    }

    public function deleteCustomer(string $customerId): array
    {
        return $this->customerService->deleteCustomer($customerId);
    }

    public function updateCustomerData(string $customerId, array $fields): array
    {
        return $this->customerService->updateCustomerData($customerId, $fields);
    }

    public function issueInvoice(string $paymentId, array $invoiceData = []): array
    {
        return $this->invoiceService->issueInvoiceForPayment($paymentId, $invoiceData);
    }

    public function getInvoice(string $invoiceId): array
    {
        return $this->invoiceService->getInvoice($invoiceId);
    }

    public function listInvoices(array $filters = []): array
    {
        return $this->invoiceService->listInvoices($filters);
    }

    public function cancelInvoice(string $invoiceId): array
    {
        return $this->invoiceService->cancelInvoice($invoiceId);
    }

    public function processWebhook(string $rawBody, array $headers, string $remoteIp, string $httpMethod = 'POST'): array
    {
        return $this->webhookService->processWebhook($rawBody, $headers, $remoteIp, $httpMethod);
    }
}
