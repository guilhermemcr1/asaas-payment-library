# Ação `list_customers`

## Finalidade

Listar clientes por período ou buscar um cliente específico.

## Modos

1. Por ID: informe `customerId` (retorna `data.item`).
2. Por período: informe `startDate` e `endDate` (`YYYY-MM-DD`).

Filtros opcionais: `name`, `email`, `cpfCnpj`, `externalReference`, `offset`, `limit` (padrão `100` via `config/listagens.php` quando omitido).

## Exemplo HTTP (período)

```json
{
  "action": "list_customers",
  "startDate": "2026-05-01",
  "endDate": "2026-05-31",
  "limit": 100
}
```

## Exemplo `AsaasGateway`

```php
$gateway->listCustomers([
    'startDate' => '2026-05-01',
    'endDate' => '2026-05-31',
]);
```

Mais formas de chamada: [`USO_CHAMADAS.md`](./USO_CHAMADAS.md).
