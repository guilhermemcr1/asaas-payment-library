# Asaas Biblioteca (Fase 1)

Biblioteca externa para integração com a API da Asaas, criada para oferecer um contrato estável e reaproveitável em qualquer projeto.

## Escopo desta fase

- Criar base da biblioteca.
- Implementar emissão (PIX, boleto, link de pagamento, assinatura).
- Implementar webhook seguro e auditável.
- Não alterar scripts legados da EFI.

## Estrutura

- `src/AsaasGateway.php` fachada in-process (métodos diretos).
- `src/Config/AsaasConfig.php` configuração por array/env.
- `src/Http/AsaasHttpClient.php` cliente HTTP.
- `src/Services/*` regras por domínio.
- `src/Security/WebhookAuthGuard.php` segurança do webhook.
- `src/Audit/AsaasEventLogger.php` trilha de auditoria.
- `sql/asaas_event_log.sql` tabela de eventos.
- `sql/asaas_fila_processamento.sql` tabela de idempotência/fila de processamento.
- `public/index.php` endpoint HTTP único da biblioteca.
- `config/options.php` infraestrutura da biblioteca.
- `config/*.php` por ação (`issue_invoice`, `create_payment_pix`, `create_payment_card_link`, `create_subscription`, etc.).

## Configuração centralizada (estilo EFI)

A biblioteca pode carregar automaticamente `config/options.php` + `config/helpers.php`, usando detecção de ambiente por host (PROD/DEV) no mesmo padrão da SDK EFI.

Arquivo esperado:

- `config/options.php`

Campos principais:

- `prod_hosts` (hosts que identificam produção)
- `dev_hosts` (hosts que identificam desenvolvimento/sandbox)
- `environment` (`auto`, `production` ou `sandbox`)
- `api.api_key_prod` / `api.api_key_sandbox`
- `api.base_url_prod` / `api.base_url_sandbox`
- `webhook.token_prod` / `webhook.token_sandbox`
- `webhook.token_header`
- `webhook.allowed_ips`
- `api.user_agent_base` (prefixo do User-Agent com sufixo automático de ambiente/host)
- `debug.enabled` / `debug.safe_details` (detalhamento seguro de erros)
- `internal.http_api_enabled` (habilita chamadas internas via HTTP/cURL; webhook segue ativo)

Prioridade de resolução:

1. config explícita injetada no `AsaasGateway`
2. variáveis de ambiente (chaves sensíveis: API, webhook, debug, HTTP interno)
3. `config/options.php`
4. fallback em código

### Matriz de precedência por chave sensível

| Chave | Array `AsaasGateway` | Env | `options.php` | Fallback em código |
|-------|----------------------|-----|---------------|--------------------|
| Ambiente | `environment` ou legado `sandbox` | `ASAAS_ENV` | `environment` (`auto`/`production`/`sandbox`) ou legado `sandbox` | `sandbox` |
| API key | `api_key` | `ASAAS_API_KEY` | `api.api_key_sandbox` / `api.api_key_prod` | vazio |
| Base URL API | `api_base_url` | `ASAAS_API_BASE_URL` | `api.base_url_sandbox` / `api.base_url_prod` | URL oficial Asaas |
| User-Agent | `api_user_agent` | `ASAAS_API_USER_AGENT` | `api.user_agent_base` (+ sufixo ambiente/host) | `AsaasLibrary/1.0` |
| Token webhook | `webhook_token` | `ASAAS_WEBHOOK_TOKEN` | `webhook.token_sandbox` / `webhook.token_prod` | vazio |
| Header webhook | `webhook_token_header` | — | `webhook.token_header` | `x-webhook-token` |
| IPs webhook | `webhook_allowed_ips` | `ASAAS_WEBHOOK_ALLOWED_IPS` | `webhook.allowed_ips` | `[]` |
| Filtro IP webhook | `webhook_ip_filter_enabled` | `ASAAS_WEBHOOK_IP_FILTER_ENABLED` | `webhook.ip_filter_enabled` | `true` |
| Token API interna | `internal_token` | — | `internal.token` | vazio |
| HMAC API interna | `internal_hmac_secret` | — | `internal.hmac_secret` | vazio |
| HTTP API interna | `internal_http_api_enabled` | `ASAAS_INTERNAL_HTTP_API_ENABLED` | `internal.http_api_enabled` | `true` |
| Debug | `debug_enabled` | `ASAAS_DEBUG` | `debug.enabled` | `false` |
| Debug seguro | `debug_safe_details` | — | `debug.safe_details` | `true` |
| Banco | — | — | `db.sandbox` / `db.prod` (via `isSandbox()`) | `[]` |
| Defaults por ação | `invoice_defaults`, `{feature}_defaults` | — | `config/{acao}.php` | `[]` |

## Auto-configuração DEV/PROD

Use `environment: auto` em `config/options.php` para alternar sandbox/produção pelos hosts `prod_hosts` e `dev_hosts`. Em `production` ou `sandbox`, o ambiente fica fixo. Com `auto`, host fora das listas cai em sandbox e gera aviso em log; revise as listas antes de produção.

Exemplo no `config/options.php`:

```php
'prod_hosts' => ['your-production-domain.com', 'www.your-production-domain.com'],
'dev_hosts' => ['dev.your-domain.local', 'localhost', '127.0.0.1'],
'api' => [
  'user_agent_base' => 'AsaasLibrary/1.0',
],
```

Comportamento:

- detecta ambiente pelo host comparando `prod_hosts` e `dev_hosts`
- monta User-Agent automaticamente com ambiente e host
- formato final: `api.user_agent_base (sandbox|production; host-atual)`
- fallback base: `AsaasLibrary/1.0`

Também é possível sobrescrever por variável de ambiente:

- `ASAAS_API_USER_AGENT`

## Variáveis de ambiente (fallback)

- `ASAAS_API_KEY`
- `ASAAS_API_BASE_URL` (opcional)
- `ASAAS_ENV` (`sandbox` ou `production`)
- `ASAAS_WEBHOOK_TOKEN`
- `ASAAS_WEBHOOK_ALLOWED_IPS` (lista separada por vírgula)
- `ASAAS_API_USER_AGENT` (sobrescreve User-Agent automático)
- `ASAAS_DEBUG` (`1|true|yes|on` para habilitar debug seguro)
- `ASAAS_INTERNAL_HTTP_API_ENABLED` (`0|false|no|off` desabilita chamadas internas via HTTP/cURL; webhook permanece ativo)

## Debug seguro

Bloco recomendado no `options.php`:

```php
'debug' => [
  'enabled' => false,
  'safe_details' => true,
],
```

Quando `debug.enabled=true` e `safe_details=true`, respostas de erro incluem contexto seguro:

- `environment` (`sandbox` ou `production`)
- `action`
- `httpMethod`
- `statusCode`
- `exception`

Nunca inclui segredos (`api_key`, `internal.token`, `internal.hmac_secret`) nem headers sensíveis.

## Formas de chamada

Detalhes e tabela de mapeamento: [`docs/USO_CHAMADAS.md`](docs/USO_CHAMADAS.md).

1. HTTP remoto (`public/index.php` + `action` + autenticação interna).
2. PHP in-process com `AsaasGateway` (métodos diretos).

## Uso rápido (`AsaasGateway`)

```php
<?php

require_once __DIR__ . '/src/bootstrap.php';

use AsaasBiblioteca\AsaasGateway;

$gateway = new AsaasGateway([
    'api_key' => getenv('ASAAS_API_KEY'),
    'environment' => 'sandbox',
    'webhook_token' => getenv('ASAAS_WEBHOOK_TOKEN'),
    'webhook_token_header' => 'x-webhook-token',
    'webhook_allowed_ips' => ['1.1.1.1', '2.2.2.2'],
]);

$res = $gateway->createPixCharge([
    'customer' => 'cus_000000000000',
    'value' => 99.90,
    'dueDate' => date('Y-m-d'),
    'description' => 'Cobranca de teste',
]);
```

## Uso externo via cURL (endpoint único)

Endpoint sugerido: `https://seu-dominio/asaas-biblioteca/public/index.php`

Para chamadas internas, enviar headers:

- `X-Internal-Token`
- `X-Timestamp`
- `X-Signature` (`sha256_hmac(timestamp + "." + rawBody, internal_hmac_secret)`)

Exemplo `create_payment`:

```bash
curl -X POST "https://seu-dominio/asaas-biblioteca/public/index.php" \
  -H "Content-Type: application/json" \
  -H "X-Internal-Token: SEU_TOKEN_INTERNO" \
  -H "X-Timestamp: 1714939200" \
  -H "X-Signature: SUA_ASSINATURA_HMAC" \
  -d '{
    "action":"create_payment",
    "paymentMethod":"pix",
    "customer":"cus_000000000000",
    "value":99.9,
    "dueDate":"2026-05-10",
    "description":"Cobranca de teste",
    "couponType":"PERCENTAGE",
    "couponValue":10,
    "couponDueDateLimitDays":0
  }'
```

Exemplo `get_payment_status`:

```bash
curl -X POST "https://seu-dominio/asaas-biblioteca/public/index.php" \
  -H "Content-Type: application/json" \
  -H "X-Internal-Token: SEU_TOKEN_INTERNO" \
  -H "X-Timestamp: 1714939200" \
  -H "X-Signature: SUA_ASSINATURA_HMAC" \
  -d '{"action":"get_payment_status","paymentId":"pay_123"}'
```

Exemplo `update_customer` (atualização opcional sob demanda):

```bash
curl -X POST "https://seu-dominio/asaas-biblioteca/public/index.php" \
  -H "Content-Type: application/json" \
  -H "X-Internal-Token: SEU_TOKEN_INTERNO" \
  -H "X-Timestamp: 1714939200" \
  -H "X-Signature: SUA_ASSINATURA_HMAC" \
  -d '{
    "action":"update_customer",
    "customer":"cus_000000000000",
    "customerData":{
      "name":"Empresa Exemplo LTDA",
      "email":"financeiro@empresa.com.br",
      "phone":"11999999999",
      "address":"Rua Exemplo",
      "addressNumber":"100"
    }
  }'
```

## Cupom e desconto

O fluxo recomendado é enviar cupom interno da sua aplicação e a biblioteca converter para `discount` da Asaas.

Campos aceitos:

- `couponType` (`PERCENTAGE` ou `FIXED`)
- `couponValue`
- `couponDueDateLimitDays`

Também é aceito `discount` já pronto no formato Asaas.

## NFS-e (manual)

A biblioteca suporta emissão manual de nota fiscal por:

- `action=issue_invoice` com `paymentId`

Na chamada de `issue_invoice`, você pode personalizar a descrição de 3 formas:

- `invoice.description` (prioridade mais alta)
- `description` no payload raiz (atalho)
- `invoiceDescription` no payload raiz (alias)

Também é possível solicitar emissão imediata após o agendamento:

- padrão: a biblioteca já tenta emitir automaticamente (`issueNow=true`) após agendar
- para apenas agendar sem emitir, envie `issueNow=false`

### Configuração em `config/issue_invoice.php`

Defaults fiscais da ação `issue_invoice` ficam fora de `options.php`, em arquivo dedicado da funcionalidade.

## Resolução de cliente antes da emissão

Antes de emitir cobrança/assinatura, a biblioteca resolve o cliente automaticamente:

- Se `customer` (`cus_...`) for enviado, usa esse ID.
- Se `customerData` for enviado, a biblioteca:
  - busca cliente existente por `cpfCnpj`, `externalReference` ou `email`;
  - cria cliente se não encontrar;
  - compara campos principais e atualiza cadastro quando houver diferença.

Campos suportados em `customerData`: `name`, `cpfCnpj`, `email`, `phone`, `mobilePhone`, `postalCode`, `address`, `addressNumber`, `complement`, `province`, `externalReference`.

Para atualização opcional manual, use `action=update_customer` com:

- `customer` (obrigatório): ID Asaas no formato `cus_...`
- `customerData` (obrigatório): campos parciais que deseja atualizar

Se `customer` estiver vazio ou nenhum campo for enviado em `customerData`, a API retorna erro de validação.

## Datas e vencimento suportados

- Cobranças (`/payments`): `dueDate`, `daysAfterDueDateToRegistrationCancellation`
- Assinaturas (`/subscriptions`): `nextDueDate`, `endDate`, `maxPayments`
- Link de pagamento (`/paymentLinks`): `endDate`, `dueDateLimitDays`, `subscriptionCycle`, `maxInstallmentCount`
  - quando `endDate` não é enviado, a biblioteca aplica default de expiração (D+1 por padrão)

## Contrato de saída padrão

- `transactionId`
- `link`
- `pixCode`
- `status`

Campos extras:

- `qrCodeImage`
- `pixKey`
- `expirationDate`
- `raw`

## Webhook

`processWebhook(rawBody, headers, remoteIp, httpMethod)` retorna:

- `statusCode`
- `payload` com envelope:
  - sucesso: `success=true`, `message`, `data`
  - erro: `success=false`, `message`, `errorCode`, `data`

Mapeamento HTTP definido:

- `200` sucesso
- `200` evento informativo (`log_only`)
- `200` duplicado (idempotência)
- `400` payload inválido
- `403` token/IP inválido
- `405` método inválido
- `500` erro interno

No endpoint único, quando `action` não for enviado, a biblioteca trata automaticamente como `webhook_receive`.

### Política de mapeamento de status/eventos

A biblioteca mantém 4 estados internos finais:

- `Pago`
- `Pendente`
- `Vencido`
- `Cancelado`

Eventos não financeiros (ex.: visualização, split, dunning, ciclo de assinatura sem liquidação) são tratados como `log_only`: entram na auditoria sem forçar mudança financeira.

Referência detalhada de matriz de eventos: `docs/webhook_receive.md`.

## Auditoria

A tabela de auditoria é criada pelo SQL:

- `sql/asaas_event_log.sql`

Ela registra eventos recebidos, processados, ignorados e erros com payload e metadados de origem.

Os scripts SQL usam `utf8mb4`. Bancos já criados com charset antigo precisam de `ALTER TABLE` manual para alinhar com `db.*.charset`.

## Cliente HTTP Asaas

O `AsaasHttpClient` usa `GET`, `POST` e `DELETE`; alterações na API Asaas que exigem `POST` seguem esse padrão. Timeout e retentativas vêm de `api.timeout_seconds` e `api.retry_attempts` em `config/options.php`.
