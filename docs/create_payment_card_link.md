# Ação `create_payment` — Link de Cartão

## Finalidade

Criar link de pagamento na Asaas (`/paymentLinks`) para cartão.

## Defaults em arquivo

`config/create_payment_card_link.php` define `billingType`, `chargeType`, `maxInstallmentCount`, `defaultEndDateDays` e demais campos do link. O payload da chamada sobrescreve esses valores.

## Payload

Obrigatórios:

- `action`: `create_payment`
- `paymentMethod`: `cartao`

Opcionais principais:

- `name`
- `description`
- `value`
- `endDate` (`Y-m-d`) — se não enviar, usa fallback (`defaultEndDateDays` em `config/create_payment_card_link.php`)
- `billingType` (padrão `CREDIT_CARD`)
- `chargeType` (padrão `DETACHED`)
- `dueDateLimitDays`
- `subscriptionCycle`
- `maxInstallmentCount`
- `externalReference`
- `notificationEnabled`
- `isAddressRequired`
- `callback` (link de cartão não aceita desconto, multa ou juros na biblioteca)

## Exemplo

```json
{
  "action": "create_payment",
  "paymentMethod": "cartao",
  "name": "Link Plano Premium",
  "description": "Teste link cartão",
  "value": 199.9
}
```

## Resposta esperada (sucesso)

- `data.transactionId`
- `data.link` (URL de checkout)
- `data.status` (`Pendente`)
- `data.raw`
