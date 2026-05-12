# Biblioteca Asaas — Índice de Ações

Documentação separada por ação disponível no endpoint único `public/index.php`.

Visão geral e início rápido: [README.md](../README.md). Formas de chamada (HTTP e `AsaasGateway`): [USO_CHAMADAS.md](./USO_CHAMADAS.md). Checklist de testes: [TESTES_BIBLIOTECA.md](../TESTES_BIBLIOTECA.md).

## Ações de emissão

- [create_payment (PIX)](./create_payment_pix.md)
- [create_payment (Boleto)](./create_payment_billet.md)
- [create_payment (Link Cartão)](./create_payment_card_link.md)
- [create_subscription](./create_subscription.md)

## Ações de pagamento (consulta, alteração e estorno)

- [get_payment_status](./get_payment_status.md)
- [get_pix_qrcode](./get_pix_qrcode.md)
- [update_payment](./update_payment.md)
- [refund_payment](./refund_payment.md)
- [cancel_payment](./cancel_payment.md)

## Ações de assinatura

- [get_subscription_payments](./get_subscription_payments.md)
- [update_subscription](./update_subscription.md)
- [cancel_subscription](./cancel_subscription.md)

## Ações de cliente

- [create_customer](./create_customer.md)
- [get_customer](./get_customer.md)
- [list_customers](./list_customers.md)
- [update_customer](./update_customer.md)
- [delete_customer](./delete_customer.md)
- [cliente_resolucao_automatica (usada em emissão)](./cliente_resolucao_automatica.md)
- [desconto_multas_juros (cupom, desconto, multas e juros)](./desconto_multas_juros.md)

## Ações fiscais (NFS-e)

- [issue_invoice](./issue_invoice.md)
- [get_invoice](./get_invoice.md)
- [list_invoices](./list_invoices.md)
- [cancel_invoice](./cancel_invoice.md)

## Ações de webhook

- [webhook_receive](./webhook_receive.md)

## Padrão de autenticação (ações internas)

Chamadas internas via HTTP/cURL podem ser desabilitadas com `internal.http_api_enabled = false` em `config/options.php`. Nesse modo, use `AsaasGateway` in-process; `webhook_receive` permanece no `public/index.php`.

Headers obrigatórios para todas as ações internas (exceto `webhook_receive`):

- `X-Internal-Token`
- `X-Timestamp`
- `X-Signature` (`sha256_hmac(timestamp + "." + rawBody, internal_hmac_secret)`)

Erros comuns:

- `invalidInternalToken`
- `invalidSignature`
- `requestExpired`
- `ipNotAllowed`
- `httpApiDisabled`
