# Desconto, cupom, multas e juros

Guia dos campos financeiros opcionais que a biblioteca normaliza antes de chamar a API da Asaas.

## Onde se aplica

| Fluxo | Desconto / cupom | `fine` / `interest` | `split` / `callback` |
| ----- | ---------------- | ------------------- | -------------------- |
| `create_payment` (PIX) | Sim | Sim | Sim |
| `create_payment` (boleto) | Sim | Sim | Sim |
| `create_payment` (link cartão) | Não | Não | `callback` apenas |
| `create_subscription` | Sim | Sim | Sim |
| `update_payment` | Só `discount` no formato Asaas | Sim | Sim |
| `update_subscription` | Só `discount` no formato Asaas | Sim | Sim |

Na emissão, use os guias de [PIX](./create_payment_pix.md), [boleto](./create_payment_billet.md) e [assinatura](./create_subscription.md). Em alteração, veja [update_payment](./update_payment.md) e [update_subscription](./update_subscription.md).

## Duas formas de desconto na emissão

### 1. Cupom interno (atalho Finax)

Campos no payload (ou nos defaults da ação em `config/*.php`):

- `couponType`: `PERCENTAGE` ou `FIXED` (padrão `PERCENTAGE` se inválido ou ausente)
- `couponValue`: número maior que zero
- `couponDueDateLimitDays`: dias antes do vencimento em que o desconto vale (vira `dueDateLimitDays` na Asaas; padrão `0`)

Aliases aceitos: `coupon_type`, `coupon_value`, `coupon_due_date_limit_days`.

### 2. Objeto `discount` (formato Asaas)

```json
"discount": {
  "value": 10,
  "type": "PERCENTAGE",
  "dueDateLimitDays": 5
}
```

`type` aceita `PERCENTAGE` ou `FIXED`. `value` deve ser maior que zero.

## Precedência

1. Se `discount` vier no payload, a biblioteca usa só esse objeto (cupom interno é ignorado).
2. Senão, se houver cupom interno válido, converte para `discount` da Asaas.
3. Defaults de `discount`, `fine` e `interest` em `config/create_payment_pix.php`, `config/create_payment_billet.php` ou `config/create_subscription.php` entram no merge antes do payload da chamada; campos enviados na requisição sobrescrevem o arquivo.

## Vencimento quando `dueDate` não é enviado

- Cobrança PIX/boleto: `dueDateOffsetDays` em `config/create_payment_*.php` soma dias à data atual.
- Assinatura: `nextDueDateOffsetDays` em `config/create_subscription.php` define `nextDueDate` quando omitido.

## Multas e juros

`fine` e `interest` seguem o contrato da [API Asaas](https://docs.asaas.com/). A biblioteca repassa o objeto após o merge com os defaults da ação. Não há atalho interno equivalente ao cupom.

## `split` e `callback`

Objetos repassados na emissão e na atualização de cobrança/assinatura, conforme a documentação oficial da Asaas.

## Exemplo HTTP — desconto percentual com prazo

```json
{
  "action": "create_payment",
  "paymentMethod": "pix",
  "value": 199.9,
  "customer": "cus_000000000000",
  "couponType": "PERCENTAGE",
  "couponValue": 10,
  "couponDueDateLimitDays": 3,
  "description": "Plano com 10% até 3 dias antes do vencimento"
}
```

## Exemplo HTTP — desconto fixo via objeto Asaas

```json
{
  "action": "create_payment",
  "paymentMethod": "boleto",
  "value": 250,
  "customer": "cus_000000000000",
  "discount": {
    "value": 25,
    "type": "FIXED",
    "dueDateLimitDays": 0
  }
}
```

## Exemplo `AsaasGateway` — assinatura com cupom

```php
$gateway->createSubscription([
    'customer' => 'cus_000000000000',
    'value' => 79.9,
    'cycle' => 'MONTHLY',
    'couponType' => 'FIXED',
    'couponValue' => 15,
    'couponDueDateLimitDays' => 0,
]);
```

## Exemplo — alterar desconto de cobrança existente

Só o objeto `discount` (sem cupom interno):

```php
$gateway->updatePayment('pay_123', [
    'paymentData' => [
        'discount' => [
            'value' => 5,
            'type' => 'PERCENTAGE',
            'dueDateLimitDays' => 2,
        ],
    ],
]);
```

## Defaults em arquivo (exemplo)

Em `config/create_payment_pix.php`:

```php
'discount' => [
    'value' => 5,
    'type' => 'PERCENTAGE',
    'dueDateLimitDays' => 0,
],
'fine' => null,
'interest' => null,
```

Valores `null` ou omitidos não são enviados à Asaas.
