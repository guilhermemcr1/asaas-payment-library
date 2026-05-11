# Ação `create_subscription`

## Finalidade

Criar assinatura na Asaas (`/subscriptions`).

## Defaults em arquivo

`config/create_subscription.php` define `billingType`, `cycle`, `nextDueDateOffsetDays`, multas/juros e demais campos padrão. O payload da chamada sobrescreve esses valores.

## Payload

Obrigatórios:

- `action`: `create_subscription`
- `value`

Cliente:

- `customer` **ou** `customerData`

Opcionais principais:

- `billingType` (padrão `CREDIT_CARD`)
- `nextDueDate` (`Y-m-d`)
- `cycle` (padrão `MONTHLY`)
- `description`
- `endDate` (`Y-m-d`)
- `maxPayments`
- `externalReference`
- `discount` ou cupom (`couponType`, `couponValue`, `couponDueDateLimitDays`)
- `fine`, `interest`, `split`, `callback`

## Exemplo

```json
{
  "action": "create_subscription",
  "value": 79.9,
  "cycle": "MONTHLY",
  "nextDueDate": "2026-05-10",
  "customerData": {
    "name": "Empresa Assinante LTDA",
    "cpfCnpj": "12345678000199",
    "email": "financeiro@assinante.com.br"
  }
}
```

## Resposta esperada (sucesso)

- `data.transactionId` (id da assinatura)
- `data.link`
- `data.status`
- `data.raw`
