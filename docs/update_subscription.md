# Ação `update_subscription`

## Finalidade

Atualizar uma assinatura existente.

## Payload HTTP

- `action`: `update_subscription`
- `subscriptionId`: `sub_...`
- `subscriptionData`: campos a alterar (`value`, `nextDueDate`, `cycle`, `billingType`, `updatePendingPayments`, `discount`, `fine`, `interest`, `split`, etc.)

Na atualização, use `discount` no formato Asaas; cupom interno não é convertido neste fluxo. Ver [desconto_multas_juros.md](./desconto_multas_juros.md).

## Exemplo HTTP

```json
{
  "action": "update_subscription",
  "subscriptionId": "sub_123",
  "subscriptionData": {
    "value": 149.9,
    "updatePendingPayments": true
  }
}
```

## Exemplo `AsaasGateway`

```php
$gateway->updateSubscription('sub_123', [
    'subscriptionData' => ['value' => 149.9],
]);
```

Mais formas de chamada: [USO_CHAMADAS.md](./USO_CHAMADAS.md).
