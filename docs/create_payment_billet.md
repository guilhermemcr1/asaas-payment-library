# Ação `create_payment` — Boleto

## Finalidade

Criar cobrança por boleto na Asaas e retornar `transactionId`, `link` e status interno.

## Defaults em arquivo

`config/create_payment_billet.php` define vencimento (`dueDateOffsetDays`), descrição, desconto, multas/juros e demais campos padrão. O payload da chamada sobrescreve esses valores. Ver [`desconto_multas_juros.md`](./desconto_multas_juros.md).

## Payload

Obrigatórios:

- `action`: `create_payment`
- `paymentMethod`: `boleto`
- `value`

Cliente:

- `customer` **ou** `customerData`

Opcionais:

- `dueDate` (`Y-m-d`); se omitido, usa hoje + `dueDateOffsetDays` do arquivo de config
- `description`
- `externalReference`
- `daysAfterDueDateToRegistrationCancellation`
- `postalService`
- `discount` ou cupom (`couponType`, `couponValue`, `couponDueDateLimitDays` e aliases `coupon_*`)
- `fine`, `interest`, `split`, `callback` (detalhes em [`desconto_multas_juros.md`](./desconto_multas_juros.md))

## Exemplo

```json
{
  "action": "create_payment",
  "paymentMethod": "boleto",
  "value": 149.9,
  "dueDate": "2026-05-15",
  "description": "Teste boleto",
  "customer": "cus_000000000000"
}
```

## Resposta esperada (sucesso)

- `data.transactionId`
- `data.link` (`invoiceUrl`)
- `data.status`
- `data.raw`
