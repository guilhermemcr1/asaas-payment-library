# Ação `webhook_receive`

## Finalidade

Receber eventos do webhook da Asaas com validação de segurança, auditoria e idempotência.

## Observações de roteamento

- Se `action` não for enviada no body, o endpoint assume automaticamente `webhook_receive`.
- Esta ação usa validação de webhook (`token + IP`) e não o fluxo de autenticação interna por HMAC.

## Segurança

Validações aplicadas:

- método HTTP deve ser `POST`
- token no header configurado (`webhook.token_header`)
- IP em `webhook.allowed_ips`

## Comportamento de processamento

- parse de `eventId`, `eventType`, `transactionId/status`
- validação de evento mínimo
- idempotência via `asaas_fila_processamento` (por `eventId`)
- auditoria em `asaas_event_log`
- política `log_only` para eventos informativos (sem forçar mudança financeira)

## Matriz de mapeamento (4 estados internos)

Estados internos finais da biblioteca:

- `Pago`
- `Pendente`
- `Vencido`
- `Cancelado`

### Cobrança — eventos mapeados como financeiros

- `PAYMENT_CONFIRMED` -> `Pago`
- `PAYMENT_RECEIVED` -> `Pago`
- `PAYMENT_ANTICIPATED` -> `Pago`
- `PAYMENT_OVERDUE` -> `Vencido`
- `PAYMENT_DELETED` -> `Cancelado`
- `PAYMENT_REFUNDED` -> `Cancelado`
- `PAYMENT_REFUND_IN_PROGRESS` -> `Cancelado`
- `PAYMENT_REFUND_DENIED` -> `Cancelado`
- `PAYMENT_RECEIVED_IN_CASH_UNDONE` -> `Cancelado`
- `PAYMENT_CHARGEBACK_REQUESTED` -> `Cancelado`
- `PAYMENT_CHARGEBACK_DISPUTE` -> `Cancelado`
- `PAYMENT_AWAITING_CHARGEBACK_REVERSAL` -> `Cancelado`
- `PAYMENT_BANK_SLIP_CANCELLED` -> `Cancelado`
- `PAYMENT_CREDIT_CARD_CAPTURE_REFUSED` -> `Cancelado`
- `PAYMENT_PARTIALLY_REFUNDED` -> `Cancelado`

### Cobrança/Assinatura — eventos `log_only`

Esses eventos são apenas auditados (`processingStatus = log_only`):

- `PAYMENT_CREATED`
- `PAYMENT_UPDATED`
- `PAYMENT_AUTHORIZED`
- `PAYMENT_AWAITING_RISK_ANALYSIS`
- `PAYMENT_APPROVED_BY_RISK_ANALYSIS`
- `PAYMENT_REPROVED_BY_RISK_ANALYSIS`
- `PAYMENT_RESTORED`
- `PAYMENT_DUNNING_RECEIVED`
- `PAYMENT_DUNNING_REQUESTED`
- `PAYMENT_BANK_SLIP_VIEWED`
- `PAYMENT_CHECKOUT_VIEWED`
- `PAYMENT_SPLIT_CANCELLED`
- `PAYMENT_SPLIT_DIVERGENCE_BLOCK`
- `PAYMENT_SPLIT_DIVERGENCE_BLOCK_FINISHED`
- `SUBSCRIPTION_CREATED`
- `SUBSCRIPTION_UPDATED`
- `SUBSCRIPTION_INACTIVATED`
- `SUBSCRIPTION_DELETED`
- `SUBSCRIPTION_SPLIT_DISABLED`
- `SUBSCRIPTION_SPLIT_DIVERGENCE_BLOCK`
- `SUBSCRIPTION_SPLIT_DIVERGENCE_BLOCK_FINISHED`

## Respostas esperadas

- `200` evento processado
- `200` evento informativo registrado (`log_only`)
- `200` evento duplicado ignorado
- `400` payload inválido / evento inválido
- `403` token/IP inválido
- `405` método inválido

## Campos de retorno (sucesso)

- `data.eventId`
- `data.eventType`
- `data.transactionId`
- `data.status`
- `data.raw` (apenas quando evento novo)
