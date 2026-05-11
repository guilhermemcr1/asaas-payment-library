# Ação `get_payment_status`

## Finalidade

Consultar status de cobrança na Asaas.

## Payload

Obrigatórios:

- `action`: `get_payment_status`
- `paymentId`: `pay_...`

## Exemplo

```json
{
  "action": "get_payment_status",
  "paymentId": "pay_123"
}
```

## Resposta esperada (sucesso)

- `data.transactionId`
- `data.link`
- `data.status`
- `data.raw`
