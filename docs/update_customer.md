# Ação `update_customer`

## Finalidade

Atualizar dados de cliente na Asaas sob demanda.

## Payload

Obrigatórios:

- `action`: `update_customer`
- `customer`: `cus_...`
- `customerData`: objeto com ao menos 1 campo

Campos aceitos em `customerData`:

- `name`
- `cpfCnpj`
- `email`
- `phone`
- `mobilePhone`
- `postalCode`
- `address`
- `addressNumber`
- `complement`
- `province`
- `externalReference`
- `company`
- `notificationDisabled`
- `additionalEmails`
- `municipalInscription`
- `stateInscription`
- `observations`
- `groupName`

## Exemplo

```json
{
  "action": "update_customer",
  "customer": "cus_000000000000",
  "customerData": {
    "email": "financeiro@empresa.com.br",
    "phone": "11999998888",
    "address": "Rua Exemplo",
    "addressNumber": "100"
  }
}
```

## Resposta esperada

Sucesso:

- `success=true`
- `data.customer`
- `data.updatedFields`
- `data.raw`

Validação:

- sem `customer` => `validationError`
- sem `customerData` => `validationError`
