# Ação `get_pix_qrcode`

## Finalidade

Consultar QRCode PIX e payload copia-e-cola de um pagamento.

## Payload

Obrigatórios:

- `action`: `get_pix_qrcode`
- `paymentId`: `pay_...`

## Exemplo

```json
{
  "action": "get_pix_qrcode",
  "paymentId": "pay_123"
}
```

## Resposta esperada (sucesso)

- `data.paymentId`
- `data.pixCode`
- `data.qrCodeImage`
- `data.pixKey`
- `data.expirationDate`
- `data.raw`
