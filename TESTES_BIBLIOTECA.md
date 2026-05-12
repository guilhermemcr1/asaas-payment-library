# Plano de testes — Asaas Biblioteca

Checklist para validar a biblioteca em sandbox e, depois, repetir os cenários críticos em produção.

Visão geral do projeto: [README.md](README.md). Formas de chamada (HTTP e `AsaasGateway`): [docs/USO_CHAMADAS.md](docs/USO_CHAMADAS.md). Índice de ações: [docs/ACOES_INDICE.md](docs/ACOES_INDICE.md).

## 0) Testes automatizados locais

- [ ] `php tests/contract_test.php`
- [ ] `php tests/security_test.php`
- [ ] Merge de defaults por ação (ex.: `maxInstallmentCount` 5 no arquivo e 3 no payload)
- [ ] `create_payment` com `paymentMethod` inválido (`validationError`)
- [ ] Claim idempotente do webhook (segundo `eventId` ignorado)
- [ ] Carregamento de cada `config/*.php` de feature com chave `defaults`
- [ ] Resolução de `environment` (`production` / `sandbox`) e IDs obrigatórios no `ActionRouter`

## 1) Pré-requisitos

- [ ] `config/options.php` criado a partir de [config/options_example.php](config/options_example.php) (não versionar credenciais reais).
- [ ] `prod_hosts`, `dev_hosts` e `environment` (`auto`, `sandbox` ou `production`) coerentes com o deploy.
- [ ] Chaves e tokens Asaas preenchidos (preferir variáveis de ambiente em produção).
- [ ] `internal.allowed_ips` com IPs reais do servidor chamador (sem hostnames).
- [ ] Tabelas criadas:
  - [ ] `sql/asaas_event_log.sql`
  - [ ] `sql/asaas_fila_processamento.sql`
- [ ] Charset das tabelas alinhado a `utf8mb4` quando o banco já existia antes do script.
- [ ] Webhook configurado no painel Asaas apontando para `public/index.php`.
- [ ] Endpoint da biblioteca acessível por HTTPS.

## 2) Smoke de autenticação interna

Com `internal.http_api_enabled = false`, uma `action` interna deve retornar HTTP `403` e `errorCode = httpApiDisabled`. O webhook (`webhook_receive` ou POST sem `action`) deve continuar processável.

Objetivo: validar token, HMAC, timestamp e IP sem executar regra de negócio.

- [ ] Enviar `action` inválida (`__smoke_test__`).
- [ ] Esperado: HTTP `400`, `errorCode = invalid_action`.

Se falhar:

- `403 ipNotAllowed`: revisar `internal.allowed_ips`
- `403 invalidInternalToken`: revisar token
- `403 invalidSignature`: revisar HMAC (`timestamp + "." + rawBody`)
- `403 requestExpired`: revisar relógio ou janela de timestamp

## 3) Contrato HTTP (`ActionRouter`)

- [ ] `create_payment` sem `paymentMethod` válido: HTTP `400`, `validationError`.
- [ ] `update_payment` sem `paymentId`: HTTP `400`, `validationError`.
- [ ] `update_subscription` sem `subscriptionId`: HTTP `400`, `validationError`.
- [ ] `issue_invoice` sem `paymentId`: HTTP `400`, `validationError`.
- [ ] `get_invoice` / `cancel_invoice` sem `invoiceId`: HTTP `400`, `validationError`.
- [ ] `update_customer` sem `customer`/`customerId` ou sem `customerData`: HTTP `400`, `validationError`.

## 4) Pagamentos

### 4.1 PIX (`create_payment` + `paymentMethod=pix`)

- [ ] Criar cobrança com `customer` ou `customerData`.
- [ ] Esperado: `success=true`, `data.transactionId`, `data.pixCode`, `data.status`.

### 4.2 Boleto (`paymentMethod=boleto`)

- [ ] Criar cobrança boleto.
- [ ] Esperado: `success=true`, `data.transactionId`, `data.link`, `data.status`.

### 4.3 Link cartão (`paymentMethod=cartao`)

- [ ] Criar link de pagamento.
- [ ] Esperado: `success=true`, `data.transactionId`, `data.link`, `data.status = Pendente`.

### 4.4 Assinatura (`create_subscription`)

- [ ] Criar assinatura com `value`, `cycle` e `nextDueDate` (ou defaults de `config/create_subscription.php`).
- [ ] Esperado: `success=true`, `data.transactionId`, `data.status`.

## 5) Consulta, alteração e cancelamento

- [ ] `get_payment_status` com `paymentId` válido.
- [ ] `get_pix_qrcode` em cobrança PIX.
- [ ] `update_payment` em cobrança aberta (`dueDate` ou `description`).
- [ ] `refund_payment` total ou parcial (`refund.value`).
- [ ] `cancel_payment` em cobrança aberta.
- [ ] `get_subscription_payments` com `subscriptionId`, `limit` e `offset`.
- [ ] `update_subscription` com `subscriptionData`.
- [ ] `cancel_subscription` em assinatura ativa.
- [ ] Repetir fluxos críticos via `AsaasGateway` in-process quando o consumidor for PHP no mesmo servidor.

## 6) Clientes

### 6.1 CRUD

- [ ] `create_customer`, `get_customer`, `list_customers` (por `customerId` ou `startDate`/`endDate`), `delete_customer`.
- [ ] Listagem via `AsaasGateway::listCustomers()`.

### 6.2 Resolução automática na emissão

- [ ] `create_payment` só com `customerData`: cliente resolvido e cobrança emitida.
- [ ] `create_payment` com `customer` + `customerData` divergente: sync antes da emissão.

### 6.3 `update_customer`

- [ ] Payload válido: `success=true`, `data.customer`, `data.updatedFields`.
- [ ] Sem `customer` ou sem `customerData`: `validationError`.

## 7) Cupom, datas e NFS-e

- [ ] Cupom interno (`couponType`, `couponValue`, `couponDueDateLimitDays`) ou `discount` no formato Asaas.
- [ ] Datas em cobrança (`dueDate`), assinatura (`nextDueDate`, `endDate`) e link (`endDate`, fallback `defaultEndDateDays`).
- [ ] `issue_invoice` com `paymentId` e override em `invoice` / `description`.
- [ ] `issueNow=true` (padrão em `config/issue_invoice.php`) e `issueNow=false` (apenas agendar).
- [ ] `get_invoice`, `list_invoices`, `cancel_invoice`.

## 8) Webhook e idempotência

### 8.1 Webhook válido

- [ ] POST com token correto no header configurado.
- [ ] Se `webhook.ip_filter_enabled=true`, validar IP em `webhook.allowed_ips`.
- [ ] Esperado: HTTP `200`, registro em `asaas_event_log` e claim em `asaas_fila_processamento`.

### 8.2 Reenvio do mesmo `eventId`

- [ ] Reenviar o mesmo evento.
- [ ] Esperado: HTTP `200`, duplicidade tratada, sem reprocessamento lógico nem erro 500 por UNIQUE em auditoria.

### 8.3 Webhook inválido

- [ ] Token inválido, método HTTP inválido, payload inválido.
- [ ] Com filtro de IP ativo: origem fora da allowlist.
- [ ] Esperado: `403` / `405` / `400` conforme cenário; trilha de auditoria coerente em falha de auth.

## 9) Segurança (ações internas)

- [ ] Token interno inválido.
- [ ] Assinatura HMAC inválida.
- [ ] Timestamp fora da janela.
- [ ] IP não permitido.
- [ ] Esperado: bloqueio sem execução de ação de negócio.

## 10) Debug, ambiente e erros públicos

- [ ] `debug.enabled=true` e `debug.safe_details=true`: contexto seguro em erro (`environment`, `action`, `httpMethod`, `statusCode`, `exception`), sem segredos.
- [ ] `debug.enabled=false`: resposta genérica sem corpo bruto da Asaas.
- [ ] `environment: auto` com host em `prod_hosts` => produção; em `dev_hosts` => sandbox.
- [ ] Host não mapeado em `auto`: sandbox com aviso em log.
- [ ] User-Agent: `user_agent_base (environment; host-atual)`.

## 11) Banco e persistência

- [ ] `id` UUID em `asaas_event_log` e `asaas_fila_processamento`.
- [ ] Inserts via repositórios em fluxo de webhook.
- [ ] Falha simulada de persistência não derruba emissão in-process nem resposta HTTP do webhook após decisão de negócio.

## 12) Critérios de aceite final

- [ ] Autenticação interna, emissão PIX/boleto/cartão/assinatura, consultas, webhook e idempotência aprovados em sandbox.
- [ ] Cenários críticos repetidos em produção com credenciais e hosts corretos.
- [ ] Nenhum vazamento de segredo em respostas de erro.
- [ ] `config/options.php` fora do controle de versão; deploy documentado.
- [ ] Logs e tabelas de auditoria consistentes.
- [ ] Integração pronta para consumo pelo sistema de faturamento (checkout, baixa de fatura ou fila de negócio), conforme o produto que chama a biblioteca.
