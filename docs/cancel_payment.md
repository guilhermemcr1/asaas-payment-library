# Ação `cancel_payment`

## Finalidade

Cancelar cobrança na Asaas.

## Payload

Obrigatórios:

- `action`: `cancel_payment`
- `paymentId`: `pay_...`

## Exemplo

```json
{
  "action": "cancel_payment",
  "paymentId": "pay_123"
}
```

## Resposta esperada (sucesso)

- `data.transactionId`
- `data.raw`
