# Ação `cancel_subscription`

## Finalidade

Cancelar assinatura na Asaas.

## Payload

Obrigatórios:

- `action`: `cancel_subscription`
- `subscriptionId`: `sub_...`

## Exemplo

```json
{
  "action": "cancel_subscription",
  "subscriptionId": "sub_123"
}
```

## Resposta esperada (sucesso)

- `data.transactionId`
- `data.raw`
