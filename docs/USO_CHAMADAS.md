# Formas de chamar a biblioteca

A biblioteca oferece dois caminhos para a mesma regra de negócio.

Configure o deploy copiando [config/options_example.php](../config/options_example.php) para `config/options.php` e preenchendo credenciais fora do Git. Visão geral: [README.md](../README.md).

## 1. HTTP remoto (cURL)

Use quando o chamador estiver em outro servidor ou processo. Envie `POST` JSON para `public/index.php` com autenticação interna (`X-Internal-Token`, `X-Timestamp`, `X-Signature`).

Esse caminho pode ser desligado em `config/options.php` com `internal.http_api_enabled = false` (ou `ASAAS_INTERNAL_HTTP_API_ENABLED=false`). O `webhook_receive` continua ativo no mesmo `public/index.php`.

```bash
curl -X POST "https://seu-dominio/asaas-biblioteca/public/index.php" \
  -H "Content-Type: application/json" \
  -H "X-Internal-Token: SEU_TOKEN" \
  -H "X-Timestamp: 1714939200" \
  -H "X-Signature: SUA_ASSINATURA_HMAC" \
  -d '{"action":"list_customers","startDate":"2026-05-01","endDate":"2026-05-31"}'
```

## 2. PHP in-process com `AsaasGateway`

Use no mesmo processo PHP, sem autenticação interna. A config vem de array, `config/options.php` ou variáveis de ambiente.

```php
require_once __DIR__ . '/asaas-biblioteca/src/bootstrap.php';

use AsaasBiblioteca\AsaasGateway;

$gateway = new AsaasGateway();
$result = $gateway->listCustomers([
    'startDate' => '2026-05-01',
    'endDate' => '2026-05-31',
]);
```

## Ambiente (`environment`)

Em `config/options.php`, use `environment` com `auto`, `production` ou `sandbox`. Com `auto`, a biblioteca escolhe sandbox ou produção pelos hosts `prod_hosts` e `dev_hosts`. Host fora das listas cai em sandbox e registra aviso em log.

Precedência: array injetado no `AsaasGateway` → `ASAAS_ENV` → `options.environment` → legado `options.sandbox` → fallback sandbox.

## Defaults por ação

Regras de negócio ficam em arquivos dedicados em `config/` (ex.: `create_payment_card_link.php`, `issue_invoice.php`, `http_actions.php`, `listagens.php`). Na chamada, campos enviados no payload sobrescrevem o arquivo.

Em `create_payment` via HTTP, `paymentMethod` pode ser omitido quando `config/http_actions.php` define `defaultPaymentMethod` (padrão `pix`). Listagens sem `limit` usam `defaultLimit` de `config/listagens.php` (padrão `100`).

Desconto, cupom, multas e juros: [desconto_multas_juros.md](./desconto_multas_juros.md).

## Resposta padrão

Ambos os caminhos retornam o mesmo formato:

- `success` (bool)
- `message` (string)
- `data` (array)
- `errorCode` (string, em falhas de validação)

No endpoint HTTP, detalhes de erro da Asaas só aparecem com `debug.enabled` e `debug.safe_details` ativos. Fora disso, a resposta mantém mensagem genérica e `errorCode`.

Em `create_payment`, `paymentMethod` inválido retorna HTTP `400` com `errorCode = validationError` (valores aceitos: `pix`, `boleto`, `cartao`).

No HTTP, o `ActionRouter` valida IDs obrigatórios por ação (`paymentId`, `subscriptionId`, `customerId`/`customer`, `invoiceId`) antes de chamar o gateway. Ausência retorna HTTP `400` com `validationError`.

## Mapeamento rápido

| Ação HTTP (`action`) | `AsaasGateway` |
|----------------------|----------------|
| `create_customer` | `createCustomer()` |
| `get_customer` | `getCustomer()` |
| `list_customers` | `listCustomers()` |
| `update_customer` | `updateCustomerData()` |
| `delete_customer` | `deleteCustomer()` |
| `create_payment` | `createPixCharge()` / `createBilletCharge()` / `createCardPaymentLink()` |
| `get_payment_status` | `getPaymentStatus()` |
| `get_pix_qrcode` | `getPixQrCodeByPaymentId()` |
| `update_payment` | `updatePayment()` |
| `refund_payment` | `refundPayment()` |
| `cancel_payment` | `cancelPayment()` |
| `create_subscription` | `createSubscription()` |
| `update_subscription` | `updateSubscription()` |
| `get_subscription_payments` | `getSubscriptionPayments()` |
| `cancel_subscription` | `cancelSubscription()` |
| `issue_invoice` | `issueInvoice()` |
| `get_invoice` | `getInvoice()` |
| `list_invoices` | `listInvoices()` |
| `cancel_invoice` | `cancelInvoice()` |
