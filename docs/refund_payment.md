# Ação `refund_payment`

## Finalidade

Estornar uma cobrança recebida ou confirmada.

## Payload HTTP

- `action`: `refund_payment`
- `paymentId`: `pay_...`
- `refund` (opcional): `value` (parcial), `description`

## Exemplo HTTP

```json
{
  "action": "refund_payment",
  "paymentId": "pay_123",
  "refund": {
    "value": 50.0,
    "description": "Estorno parcial"
  }
}
```

## Exemplo `AsaasGateway`

```php
$gateway->refundPayment('pay_123', ['value' => 50.0]);
```

Mais formas de chamada: [USO_CHAMADAS.md](./USO_CHAMADAS.md).
