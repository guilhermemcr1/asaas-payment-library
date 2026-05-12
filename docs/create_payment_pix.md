# Ação `create_payment` — PIX

## Finalidade

Criar cobrança PIX na Asaas e retornar `transactionId`, `pixCode`, imagem QRCode e status interno.

## Defaults em arquivo

`config/create_payment_pix.php` define vencimento (`dueDateOffsetDays`), descrição, desconto, multas/juros e demais campos padrão. O payload da chamada sobrescreve esses valores. Ver [desconto_multas_juros.md](./desconto_multas_juros.md).

## Payload

Obrigatórios:

- `action`: `create_payment`
- `paymentMethod`: `pix` (em `create_payment`, os valores aceitos são `pix`, `boleto` e `cartao`)
- `value`: valor da cobrança

Cliente (uma das opções):

- `customer`: `cus_...` existente
- `customerData`: objeto para resolver/criar/atualizar cliente automaticamente

Opcionais principais:

- `dueDate` (`Y-m-d`); se omitido, usa hoje + `dueDateOffsetDays` do arquivo de config
- `description`
- `externalReference`
- `daysAfterDueDateToRegistrationCancellation`
- `postalService`
- `discount` (objeto Asaas) ou cupom interno (`couponType`, `couponValue`, `couponDueDateLimitDays` e aliases `coupon_*`)
- `fine`, `interest`, `split`, `callback` (formato Asaas; detalhes em [desconto_multas_juros.md](./desconto_multas_juros.md))

## Exemplo

```json
{
  "action": "create_payment",
  "paymentMethod": "pix",
  "value": 99.9,
  "dueDate": "2026-05-10",
  "description": "Teste PIX",
  "customerData": {
    "name": "Empresa Exemplo LTDA",
    "cpfCnpj": "12345678000199",
    "email": "financeiro@empresa.com.br"
  }
}
```

## Resposta esperada (sucesso)

- `data.transactionId`
- `data.pixCode`
- `data.qrCodeImage`
- `data.pixKey`
- `data.expirationDate`
- `data.status`
- `data.raw`
