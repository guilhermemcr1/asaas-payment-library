# Ação `list_invoices`

## Finalidade

Listar notas fiscais por período ou buscar uma nota específica.

## Modos

1. Por ID: informe `invoiceId` (retorna `data.item`).
2. Por período: informe `startDate` e `endDate` (`YYYY-MM-DD`).

Filtros opcionais: `payment`, `customer`, `status`, `offset`, `limit` (padrão `100` via `config/listagens.php` quando omitido).

## Exemplo HTTP (período)

```json
{
  "action": "list_invoices",
  "startDate": "2026-05-01",
  "endDate": "2026-05-31"
}
```

## Exemplo `AsaasGateway`

```php
$gateway->listInvoices([
    'startDate' => '2026-05-01',
    'endDate' => '2026-05-31',
]);
```

Mais formas de chamada: [USO_CHAMADAS.md](./USO_CHAMADAS.md).
