<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Contracts;

interface PaymentGatewayInterface
{
    public function createPixCharge(array $payload): array;

    public function createBilletCharge(array $payload): array;

    public function createCardPaymentLink(array $payload): array;

    public function createSubscription(array $payload): array;

    public function getPaymentStatus(string $paymentId): array;

    public function getPixQrCodeByPaymentId(string $paymentId): array;

    public function extractPixCopyPaste(array $payload): ?string;

    public function extractPixKey(array $payload): ?string;

    public function cancelPayment(string $paymentId): array;

    public function updatePayment(string $paymentId, array $fields): array;

    public function refundPayment(string $paymentId, array $options = []): array;

    public function cancelSubscription(string $subscriptionId): array;

    public function getSubscriptionPayments(string $subscriptionId, array $filters = []): array;

    public function updateSubscription(string $subscriptionId, array $fields): array;

    public function createCustomer(array $payload): array;

    public function getCustomer(string $customerId): array;

    public function listCustomers(array $filters = []): array;

    public function deleteCustomer(string $customerId): array;

    public function updateCustomerData(string $customerId, array $fields): array;

    public function issueInvoice(string $paymentId, array $invoiceData = []): array;

    public function getInvoice(string $invoiceId): array;

    public function listInvoices(array $filters = []): array;

    public function cancelInvoice(string $invoiceId): array;

    public function processWebhook(string $rawBody, array $headers, string $remoteIp, string $httpMethod = 'POST'): array;
}
