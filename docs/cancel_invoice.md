# Ação `cancel_invoice`

## Finalidade

Cancelar uma NFS-e na Asaas.

## Payload HTTP

- `action`: `cancel_invoice`
- `invoiceId`: `inv_...`

## Exemplo HTTP

```json
{
  "action": "cancel_invoice",
  "invoiceId": "inv_123"
}
```

## Exemplo `AsaasGateway`

```php
$gateway->cancelInvoice('inv_123');
```

Mais formas de chamada: [USO_CHAMADAS.md](./USO_CHAMADAS.md).
