# Ação `issue_invoice`

## Finalidade

Agendar emissão de NFS-e vinculada a uma cobrança (`paymentId`) na Asaas.

## Defaults em arquivo

`config/issue_invoice.php` define `issueNow`, descrição fiscal padrão e demais campos de NFS-e. O objeto `invoice` na chamada sobrescreve esses valores.

## Payload

Obrigatórios:

- `action`: `issue_invoice`
- `paymentId`

Opcionais:

- `invoice`: objeto de override dos defaults fiscais definidos em `config/issue_invoice.php`
- `description`: descrição personalizada direta na chamada (atalho para `invoice.description`)
- `invoiceDescription`: alias opcional para descrição personalizada
- `issueNow`: `true` para agendar e já emitir na sequência (chama internamente `/invoices/{id}/authorize`)

Campos comuns em `invoice`:

- `municipalServiceId` ou `municipalServiceCode`
- `municipalServiceName`
- `description`
- `effectiveDatePeriod`
- `taxes` (objeto)

## Exemplo

```json
{
  "action": "issue_invoice",
  "paymentId": "pay_66dke1z554coody5",
  "issueNow": true,
  "description": "Servico prestado - plano premium maio/2026",
  "invoice": {
    "municipalServiceCode": "1.07"
  }
}
```

## Regra de precedência da descrição

- Se `invoice.description` for enviado, ele tem prioridade.
- Se `invoice.description` não existir, a biblioteca usa `description` (ou `invoiceDescription`).
- Se nada for enviado na chamada, usa `defaults.description` de `config/issue_invoice.php`.

## Fluxo de emissão imediata

- `issueNow=true` (padrão): agenda e em seguida tenta emitir (`POST /invoices/{id}/authorize`).
- `issueNow=false`: apenas agenda a nota (`POST /invoices`).

## Resposta (sucesso)

- `data.paymentId`
- `data.invoiceId`
- `data.status`
- `data.raw`
