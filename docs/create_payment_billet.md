# Ação `create_payment` — Boleto

## Finalidade

Criar cobrança por boleto na Asaas e retornar `transactionId`, `link` e status interno.

## Defaults em arquivo

`config/create_payment_billet.php` define vencimento, descrição, multas/juros e demais campos padrão. O payload da chamada sobrescreve esses valores.

## Payload

Obrigatórios:

- `action`: `create_payment`
- `paymentMethod`: `boleto`
- `value`

Cliente:

- `customer` **ou** `customerData`

Opcionais:

- `dueDate` (`Y-m-d`)
- `description`
- `externalReference`
- `daysAfterDueDateToRegistrationCancellation`
- `postalService`
- `discount` ou cupom (`couponType`, `couponValue`, `couponDueDateLimitDays`)
- `fine`, `interest`, `split`, `callback`

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
