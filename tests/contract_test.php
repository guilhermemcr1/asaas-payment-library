<?php

declare(strict_types=1);

use AsaasBiblioteca\Config\AsaasConfig;
use AsaasBiblioteca\Contracts\PaymentGatewayInterface;
use AsaasBiblioteca\Http\ActionRouter;
use AsaasBiblioteca\Mappers\AsaasStatusMapper;
use AsaasBiblioteca\Support\FeaturePayloadMerger;

require_once __DIR__ . '/../src/bootstrap.php';

function assertEquals($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        echo "[OK] {$message}\n";
        return;
    }

    echo "[FAIL] {$message}. Esperado: " . var_export($expected, true) . '; Atual: ' . var_export($actual, true) . "\n";
    exit(1);
}

$mapper = new AsaasStatusMapper();
$httpApiEnabled = new AsaasConfig(['internal_http_api_enabled' => true]);
$httpApiDisabled = new AsaasConfig(['internal_http_api_enabled' => false]);
assertEquals(true, $httpApiEnabled->isInternalHttpApiEnabled(), 'HTTP API habilitada por config');
assertEquals(false, $httpApiDisabled->isInternalHttpApiEnabled(), 'HTTP API desabilitada por config');
$runtimeConfig = new AsaasConfig();
$invoiceDefaults = $runtimeConfig->getInvoiceDefaults();
assertEquals('Servico prestado', $invoiceDefaults['description'] ?? null, 'Defaults fiscais carregam de config/issue_invoice.php');
assertEquals(true, $runtimeConfig->getInvoiceIssueNowDefault(), 'issueNow default vem de config/issue_invoice.php');
assertEquals(5, $runtimeConfig->getPaymentCardLinkDefaults()['maxInstallmentCount'] ?? null, 'Parcelas default do link cartao');
assertEquals('pix', $runtimeConfig->getDefaultPaymentMethod(), 'Metodo de pagamento default em http_actions');
$mergedInstallments = FeaturePayloadMerger::merge(
    $runtimeConfig->getPaymentCardLinkDefaults(),
    ['maxInstallmentCount' => 3]
);
assertEquals(3, $mergedInstallments['maxInstallmentCount'] ?? null, 'Payload sobrescreve default de parcelas');
assertEquals('Pago', $mapper->toInternalStatus('RECEIVED'), 'Mapear RECEIVED para Pago');
assertEquals('Pendente', $mapper->toInternalStatus('PENDING'), 'Mapear PENDING para Pendente');
assertEquals('Vencido', $mapper->toInternalStatus('OVERDUE'), 'Mapear OVERDUE para Vencido');
assertEquals('Cancelado', $mapper->toInternalStatus('DELETED'), 'Mapear DELETED para Cancelado');

$gateway = new class implements PaymentGatewayInterface {
    public function createPixCharge(array $payload): array { return ['success' => true]; }
    public function createBilletCharge(array $payload): array { return ['success' => true]; }
    public function createCardPaymentLink(array $payload): array { return ['success' => true]; }
    public function createSubscription(array $payload): array { return ['success' => true]; }
    public function getPaymentStatus(string $paymentId): array { return ['success' => true]; }
    public function getPixQrCodeByPaymentId(string $paymentId): array { return ['success' => true]; }
    public function extractPixCopyPaste(array $payload): ?string { return null; }
    public function extractPixKey(array $payload): ?string { return null; }
    public function cancelPayment(string $paymentId): array { return ['success' => true]; }
    public function updatePayment(string $paymentId, array $fields): array { return ['success' => true]; }
    public function refundPayment(string $paymentId, array $options = []): array { return ['success' => true]; }
    public function cancelSubscription(string $subscriptionId): array { return ['success' => true]; }
    public function getSubscriptionPayments(string $subscriptionId, array $filters = []): array { return ['success' => true]; }
    public function updateSubscription(string $subscriptionId, array $fields): array { return ['success' => true]; }
    public function createCustomer(array $payload): array { return ['success' => true]; }
    public function getCustomer(string $customerId): array { return ['success' => true]; }
    public function listCustomers(array $filters = []): array
    {
        if ($filters === []) {
            return [
                'success' => false,
                'errorCode' => 'validationError',
                'message' => 'Provide customerId or both startDate and endDate.',
                'data' => [],
            ];
        }

        return ['success' => true, 'data' => []];
    }
    public function deleteCustomer(string $customerId): array { return ['success' => true]; }
    public function updateCustomerData(string $customerId, array $fields): array { return ['success' => true]; }
    public function issueInvoice(string $paymentId, array $invoiceData = []): array { return ['success' => true]; }
    public function getInvoice(string $invoiceId): array { return ['success' => true]; }
    public function listInvoices(array $filters = []): array { return ['success' => true]; }
    public function cancelInvoice(string $invoiceId): array { return ['success' => true]; }
    public function processWebhook(string $rawBody, array $headers, string $remoteIp, string $httpMethod = 'POST'): array
    {
        return ['status_code' => 200, 'payload' => ['success' => true]];
    }
};

$router = new ActionRouter($gateway);
$invalid = $router->handle('__invalid__', [], [], '127.0.0.1', '{}', 'POST');
assertEquals(400, $invalid['status_code'], 'Action inválida retorna HTTP 400');
assertEquals('invalid_action', $invalid['payload']['errorCode'] ?? null, 'Action inválida retorna invalid_action');

$newActions = [
    'update_payment' => ['paymentId' => 'pay_contract_test'],
    'refund_payment' => ['paymentId' => 'pay_contract_test'],
    'get_subscription_payments' => ['subscriptionId' => 'sub_contract_test'],
    'update_subscription' => ['subscriptionId' => 'sub_contract_test'],
    'create_customer' => ['name' => 'Contract Test Customer'],
    'get_customer' => ['customerId' => 'cus_contract_test'],
    'list_customers' => ['startDate' => '2026-01-01', 'endDate' => '2026-01-31'],
    'delete_customer' => ['customerId' => 'cus_contract_test'],
    'get_invoice' => ['invoiceId' => 'inv_contract_test'],
    'list_invoices' => ['startDate' => '2026-01-01', 'endDate' => '2026-01-31'],
    'cancel_invoice' => ['invoiceId' => 'inv_contract_test'],
];

foreach ($newActions as $action => $actionPayload) {
    $result = $router->handle($action, $actionPayload, [], '127.0.0.1', '{}', 'POST');
    assertEquals(200, $result['status_code'], "Router reconhece action {$action}");
}

$listCustomers = $router->handle('list_customers', [], [], '127.0.0.1', '{}', 'POST');
assertEquals('validationError', $listCustomers['payload']['errorCode'] ?? null, 'list_customers sem filtros retorna validationError');

$invalidPaymentMethod = $router->handle('create_payment', ['paymentMethod' => 'paypal'], [], '127.0.0.1', '{}', 'POST');
assertEquals(400, $invalidPaymentMethod['status_code'], 'paymentMethod invalido retorna HTTP 400');
assertEquals('validationError', $invalidPaymentMethod['payload']['errorCode'] ?? null, 'paymentMethod invalido retorna validationError');

$missingPaymentId = $router->handle('update_payment', [], [], '127.0.0.1', '{}', 'POST');
assertEquals(400, $missingPaymentId['status_code'], 'update_payment sem paymentId retorna HTTP 400');
assertEquals('validationError', $missingPaymentId['payload']['errorCode'] ?? null, 'update_payment sem paymentId retorna validationError');

$missingSubscriptionId = $router->handle('update_subscription', [], [], '127.0.0.1', '{}', 'POST');
assertEquals(400, $missingSubscriptionId['status_code'], 'update_subscription sem subscriptionId retorna HTTP 400');
assertEquals('validationError', $missingSubscriptionId['payload']['errorCode'] ?? null, 'update_subscription sem subscriptionId retorna validationError');

assertEquals(false, asaasResolverSandboxPorAmbiente('production', [], []), 'environment production forca API de producao');
assertEquals(true, asaasResolverSandboxPorAmbiente('sandbox', [], []), 'environment sandbox forca API de sandbox');

$productionConfig = new AsaasConfig(['environment' => 'production']);
$sandboxConfig = new AsaasConfig(['environment' => 'sandbox']);
assertEquals(false, $productionConfig->isSandbox(), 'AsaasConfig injetado com production');
assertEquals(true, $sandboxConfig->isSandbox(), 'AsaasConfig injetado com sandbox');
assertEquals('production', $productionConfig->getEnvironmentLabel(), 'Label de ambiente production');
assertEquals('sandbox', $sandboxConfig->getEnvironmentLabel(), 'Label de ambiente sandbox');

$baseDir = dirname(__DIR__);
$featureConfigs = [
    'create_payment_pix',
    'create_payment_billet',
    'create_payment_card_link',
    'create_subscription',
    'issue_invoice',
    'cliente_resolucao_automatica',
    'listagens',
    'http_actions',
];

foreach ($featureConfigs as $featureKey) {
    $featureConfig = asaasCarregarFeatureConfig($baseDir, $featureKey);
    assertEquals(true, is_array($featureConfig), "Config {$featureKey} carrega como array");
    assertEquals(true, isset($featureConfig['defaults']) && is_array($featureConfig['defaults']), "Config {$featureKey} expoe defaults");
}

$claimedEventIds = [];
$simulateClaimEventId = static function (string $eventId) use (&$claimedEventIds): bool {
    if (isset($claimedEventIds[$eventId])) {
        return false;
    }

    $claimedEventIds[$eventId] = true;
    return true;
};
assertEquals(true, $simulateClaimEventId('evt_contract_1'), 'Primeiro claim de eventId');
assertEquals(false, $simulateClaimEventId('evt_contract_1'), 'Segundo claim do mesmo eventId e ignorado');

echo "Contrato básico validado.\n";
