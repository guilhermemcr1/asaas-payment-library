# Ação `delete_customer`

## Finalidade

Remover um cliente na Asaas.

## Payload HTTP

- `action`: `delete_customer`
- `customerId` ou `customer`: `cus_...`

## Exemplo HTTP

```json
{
  "action": "delete_customer",
  "customerId": "cus_123"
}
```

## Exemplo `AsaasGateway`

```php
$gateway->deleteCustomer('cus_123');
```

Mais formas de chamada: [USO_CHAMADAS.md](./USO_CHAMADAS.md).
