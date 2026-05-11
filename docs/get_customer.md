# Ação `get_customer`

## Finalidade

Consultar um cliente pelo ID Asaas.

## Payload HTTP

- `action`: `get_customer`
- `customerId` ou `customer`: `cus_...`

## Exemplo HTTP

```json
{
  "action": "get_customer",
  "customerId": "cus_123"
}
```

## Exemplo `AsaasGateway`

```php
$gateway->getCustomer('cus_123');
```

Mais formas de chamada: [`USO_CHAMADAS.md`](./USO_CHAMADAS.md).
