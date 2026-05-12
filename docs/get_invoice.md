# Ação `get_invoice`

## Finalidade

Consultar uma NFS-e pelo ID Asaas.

## Payload HTTP

- `action`: `get_invoice`
- `invoiceId`: `inv_...`

## Exemplo HTTP

```json
{
  "action": "get_invoice",
  "invoiceId": "inv_123"
}
```

## Exemplo `AsaasGateway`

```php
$gateway->getInvoice('inv_123');
```

Mais formas de chamada: [USO_CHAMADAS.md](./USO_CHAMADAS.md).
