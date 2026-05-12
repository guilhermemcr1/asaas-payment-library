# Ação `update_payment`

## Finalidade

Atualizar uma cobrança existente na Asaas.

## Payload HTTP

- `action`: `update_payment`
- `paymentId`: `pay_...`
- `paymentData`: campos a alterar (`value`, `dueDate`, `description`, `billingType`, `discount`, `fine`, `interest`, `split`, etc.)

Na atualização, use `discount` no formato Asaas; cupom interno (`couponType` / `couponValue`) não é convertido neste fluxo. Ver [`desconto_multas_juros.md`](./desconto_multas_juros.md).

## Exemplo HTTP

```json
{
  "action": "update_payment",
  "paymentId": "pay_123",
  "paymentData": {
    "dueDate": "2026-05-20",
    "description": "Cobranca atualizada"
  }
}
```

## Exemplo `AsaasGateway`

```php
$gateway->updatePayment('pay_123', [
    'paymentData' => ['dueDate' => '2026-05-20'],
]);
```

Mais formas de chamada: [`USO_CHAMADAS.md`](./USO_CHAMADAS.md).
