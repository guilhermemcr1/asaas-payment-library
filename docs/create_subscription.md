# Ação `create_subscription`

## Finalidade

Criar assinatura na Asaas (`/subscriptions`).

## Defaults em arquivo

`config/create_subscription.php` define `billingType`, `cycle`, `nextDueDateOffsetDays`, desconto, multas/juros e demais campos padrão. O payload da chamada sobrescreve esses valores. Ver [desconto_multas_juros.md](./desconto_multas_juros.md).

## Payload

Obrigatórios:

- `action`: `create_subscription`
- `value`

Cliente:

- `customer` **ou** `customerData`

Opcionais principais:

- `billingType` (padrão `CREDIT_CARD`)
- `nextDueDate` (`Y-m-d`); se omitido, usa hoje + `nextDueDateOffsetDays` do arquivo de config
- `cycle` (padrão `MONTHLY`)
- `description`
- `endDate` (`Y-m-d`)
- `maxPayments`
- `externalReference`
- `updatePendingPayments` (bool)
- `discount` ou cupom (`couponType`, `couponValue`, `couponDueDateLimitDays` e aliases `coupon_*`)
- `fine`, `interest`, `split`, `callback` (detalhes em [desconto_multas_juros.md](./desconto_multas_juros.md))

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
