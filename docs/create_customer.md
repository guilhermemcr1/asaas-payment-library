# Ação `create_customer`

## Finalidade

Criar um cliente na Asaas de forma explícita.

## Payload HTTP

- `action`: `create_customer`
- `customerData` ou campos de cliente no topo (`name`, `cpfCnpj`, `email`, etc.)

## Exemplo HTTP

```json
{
  "action": "create_customer",
  "customerData": {
    "name": "Empresa Exemplo LTDA",
    "cpfCnpj": "12345678000199",
    "email": "financeiro@empresa.com.br"
  }
}
```

## Exemplo `AsaasGateway`

```php
$gateway->createCustomer([
    'customerData' => ['name' => 'Empresa Exemplo LTDA', 'cpfCnpj' => '12345678000199'],
]);
```

Mais formas de chamada: [`USO_CHAMADAS.md`](./USO_CHAMADAS.md).
