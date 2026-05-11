# Ação `get_subscription_payments`

## Finalidade

Listar cobranças vinculadas a uma assinatura.

## Payload HTTP

- `action`: `get_subscription_payments`
- `subscriptionId`: `sub_...`
- `offset`, `limit`, `status`, `billingType` (opcionais)

## Exemplo HTTP

```json
{
  "action": "get_subscription_payments",
  "subscriptionId": "sub_123",
  "limit": 50
}
```

## Exemplo `AsaasGateway`

```php
$gateway->getSubscriptionPayments('sub_123', ['limit' => 50]);
```

Mais formas de chamada: [`USO_CHAMADAS.md`](./USO_CHAMADAS.md).
