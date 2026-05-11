# Ação `update_subscription`

## Finalidade

Atualizar uma assinatura existente.

## Payload HTTP

- `action`: `update_subscription`
- `subscriptionId`: `sub_...`
- `subscriptionData`: campos a alterar (`value`, `nextDueDate`, `cycle`, `billingType`, `updatePendingPayments`, etc.)

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

Mais formas de chamada: [`USO_CHAMADAS.md`](./USO_CHAMADAS.md).
