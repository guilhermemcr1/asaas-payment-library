# Plano de Testes — Asaas Biblioteca

Este documento define os testes necessários para validar a `asaas-biblioteca` em ambiente Sandbox e, depois, repetir os críticos em Produção.

Formas de chamada (HTTP e `AsaasGateway`): [`docs/USO_CHAMADAS.md`](docs/USO_CHAMADAS.md).

## 0) Testes automatizados locais

- [ ] `php tests/contract_test.php`
- [ ] `php tests/security_test.php`
- [ ] `contract_test.php` valida merge de defaults por ação (ex.: `maxInstallmentCount` 5 no arquivo e 3 no payload)
- [ ] `contract_test.php` valida `create_payment` com `paymentMethod` inválido (`validationError`)
- [ ] `contract_test.php` valida claim idempotente do webhook (segundo `eventId` ignorado)
- [ ] Smoke manual: reenviar o mesmo webhook (`eventId` repetido) e confirmar HTTP `200` sem reprocessamento
- [ ] `contract_test.php` carrega cada `config/*.php` de feature e valida `defaults`
- [ ] `contract_test.php` valida `environment` (`production`/`sandbox`) e IDs obrigatórios no router

## 1) Pré-requisitos

- [ ] `config/options.php` preenchido com chaves e tokens corretos.
- [ ] `prod_hosts` e `dev_hosts` configurados.
- [ ] `internal.allowed_ips` contém o IP real do servidor chamador.
- [ ] Tabelas criadas:
  - [ ] `sql/asaas_event_log.sql`
  - [ ] `sql/asaas_fila_processamento.sql`
- [ ] Webhook configurado no painel Asaas apontando para `public/index.php`.
- [ ] Endpoint da biblioteca acessível por HTTPS.

## 2) Smoke Test de Autenticação Interna

Com `internal.http_api_enabled = false`, uma `action` interna deve retornar HTTP `403` e `errorCode = httpApiDisabled`. O webhook (`webhook_receive` ou POST sem `action`) deve continuar processável.

Objetivo: validar token + HMAC + timestamp + IP sem chamar regra de negócio.

- [ ] Enviar `action` inválida (`__smoke_test__`).
- [ ] Esperado:
  - HTTP `400`
  - `errorCode = invalid_action`

Se falhar:
- `403 ipNotAllowed`: revisar `internal.allowed_ips`
- `403 invalidInternalToken`: revisar token
- `403 invalidSignature`: revisar cálculo HMAC (`timestamp + "." + rawBody`)
- `403 requestExpired`: revisar relógio/timestamp

## 3) Testes Funcionais de Pagamentos

## 3.1 PIX (`create_payment` + `paymentMethod=pix`)

- [ ] Criar cobrança PIX com `customer` válido (`cus_...`) ou `customerData`.
- [ ] Esperado:
  - `success=true`
  - `data.transactionId` preenchido
  - `data.pixCode` preenchido
  - `data.status` preenchido

## 3.2 Boleto (`create_payment` + `paymentMethod=boleto`)

- [ ] Criar cobrança boleto.
- [ ] Esperado:
  - `success=true`
  - `data.transactionId` preenchido
  - `data.link` preenchido (`invoiceUrl`)
  - `data.status` preenchido

## 3.3 Link Cartão (`create_payment` + `paymentMethod=cartao`)

- [ ] Criar link de pagamento.
- [ ] Esperado:
  - `success=true`
  - `data.transactionId` preenchido
  - `data.link` preenchido
  - `data.status = Pendente`

## 3.4 Assinatura (`create_subscription`)

- [ ] Criar assinatura com `cycle`, `nextDueDate`, `value`.
- [ ] Esperado:
  - `success=true`
  - `data.transactionId` preenchido
  - `data.status` preenchido

## 4) Testes de Consulta e Cancelamento

## 4.1 Status (`get_payment_status`)

- [ ] Consultar status de um `paymentId` válido.
- [ ] Esperado:
  - `success=true`
  - `data.transactionId` coerente
  - `data.status` coerente com Asaas

## 4.2 QRCode PIX (`get_pix_qrcode`)

- [ ] Consultar QRCode de um pagamento PIX válido.
- [ ] Esperado:
  - `success=true`
  - `data.pixCode` preenchido
  - `data.expirationDate` quando disponível

## 4.3 Cancelamento de cobrança (`cancel_payment`)

- [ ] Cancelar cobrança aberta.
- [ ] Esperado:
  - `success=true`
  - `data.transactionId` igual ao enviado

## 4.4 Cancelamento de assinatura (`cancel_subscription`)

- [ ] Cancelar assinatura ativa.
- [ ] Esperado:
  - `success=true`
  - `data.transactionId` igual ao enviado

## 4.5 Atualização de cobrança (`update_payment`)

- [ ] Atualizar `dueDate` ou `description` de cobrança aberta.
- [ ] Validar o mesmo fluxo via `AsaasGateway::updatePayment()`.

## 4.6 Estorno (`refund_payment`)

- [ ] Estornar cobrança recebida (total ou parcial com `refund.value`).
- [ ] Validar o mesmo fluxo via `AsaasGateway::refundPayment()`.

## 4.7 Cobranças da assinatura (`get_subscription_payments`)

- [ ] Listar cobranças de `subscriptionId` com `limit` e `offset`.

## 4.8 Atualização de assinatura (`update_subscription`)

- [ ] Alterar `value` ou `nextDueDate` com `subscriptionData`.

## 5) Testes de Cliente (sync e atualização opcional)

## 5.0 CRUD explícito

- [ ] `create_customer`, `get_customer`, `list_customers` (por `customerId` ou `startDate`/`endDate`), `delete_customer`.
- [ ] Repetir listagem com `AsaasGateway::listCustomers()`.

## 5.1 Sync automático antes da emissão

- [ ] `create_payment` com `customerData` sem `customer`.
- [ ] Esperado:
  - cliente criado/identificado automaticamente
  - emissão concluída com sucesso

- [ ] `create_payment` com `customer` + `customerData` alterado.
- [ ] Esperado:
  - cliente atualizado no Asaas antes da emissão
  - emissão concluída com sucesso

## 5.2 Atualização opcional manual (`update_customer`)

- [ ] Chamada válida com `customer` + `customerData`.
- [ ] Esperado:
  - `success=true`
  - `data.customer` preenchido
  - `data.updatedFields` com campos enviados

- [ ] Chamada sem `customer`.
- [ ] Esperado:
  - `success=false`
  - `errorCode=validationError`

- [ ] Chamada sem `customerData`.
- [ ] Esperado:
  - `success=false`
  - `errorCode=validationError`

## 6) Testes de Desconto/Cupom e Datas

- [ ] Enviar `couponType`, `couponValue`, `couponDueDateLimitDays`.
- [ ] Esperado:
  - desconto aplicado sem erro de schema

- [ ] Cobrança com `dueDate`.
- [ ] Assinatura com `nextDueDate` e `endDate`.
- [ ] Link de pagamento sem `endDate`.
- [ ] Esperado:
  - regras de data aplicadas corretamente
  - fallback de expiração no link (D+1 padrão) quando omitido

## 7) Testes de Webhook e Idempotência

## 7.1 Webhook válido

- [ ] Enviar evento webhook com token/header corretos e IP permitido.
- [ ] Esperado:
  - HTTP `200`
  - registro em `asaas_event_log`
  - registro em `asaas_fila_processamento`

## 7.2 Reenvio do mesmo `eventId` (idempotência)

- [ ] Reenviar exatamente o mesmo evento.
- [ ] Esperado:
  - resposta de duplicidade tratada
  - não duplicar processamento lógico

## 7.3 Webhook inválido

- [ ] Token inválido.
- [ ] IP não permitido.
- [ ] Método HTTP inválido.
- [ ] Payload inválido.
- [ ] Esperado:
  - `403` / `405` / `400` conforme cenário
  - trilha de auditoria coerente

## 8) Testes de Segurança (ações internas)

- [ ] Token interno inválido.
- [ ] Assinatura HMAC inválida.
- [ ] Timestamp fora da janela.
- [ ] IP não permitido.
- [ ] Esperado:
  - bloqueio com código apropriado
  - sem execução de ação de negócio

## 9) Testes de Debug e Ambiente

- [ ] `debug.enabled=true` e `debug.safe_details=true`.
- [ ] Forçar erro conhecido (ex.: payload inválido).
- [ ] Esperado:
  - resposta com contexto seguro:
    - `environment`
    - `action`
    - `httpMethod`
    - `status_code`
    - `exception`
  - sem vazamento de token/chave/segredo

- [ ] `debug.enabled=false`.
- [ ] Esperado:
  - erro enxuto sem contexto extra

- [ ] Validar detecção por host:
  - host em `prod_hosts` => `production`
  - host em `dev_hosts` => `sandbox`

- [ ] Validar User-Agent dinâmico:
  - formato `user_agent_base (environment; host-atual)`
  - alternância correta entre DEV/PROD

## 10) Testes de Banco (UUID)

- [ ] Confirmar `id` em `asaas_event_log` como UUID (char(36)).
- [ ] Confirmar `id` em `asaas_fila_processamento` como UUID (char(36)).
- [ ] Confirmar inserts funcionando normalmente via repositórios.

## 11) Critérios de Aceite Final

- [ ] Todos os testes críticos aprovados: autenticação, emissão PIX/boleto/cartão, status, webhook, idempotência.
- [ ] Nenhum vazamento de segredo em respostas de erro.
- [ ] Ambiente DEV/PROD distinguido automaticamente por host.
- [ ] Logs e tabelas de auditoria consistentes.
- [ ] Pronto para iniciar migração controlada do fluxo EFI -> Asaas.

