# Função interna — Resolução automática de cliente

## Finalidade

Antes de emitir pagamento/assinatura, a biblioteca resolve o cliente automaticamente quando necessário.

## Onde é aplicada

- `create_payment` (PIX/Boleto)
- `create_subscription`

## Defaults em arquivo

`config/cliente_resolucao_automatica.php` define `fallbackName` quando o nome do cliente não é informado na criação automática (padrão `Default Customer`).

Exemplo no arquivo:

```php
'defaults' => [
    'fallbackName' => 'Cliente sem nome',
],
```

## Regras

1. Se `customer` (`cus_...`) foi enviado:
   - usa esse ID;
   - se também vier `customerData`, compara e atualiza se houver diferença.
2. Se não vier `customer`, mas vier `customerData`:
   - tenta localizar cliente por `cpfCnpj`, `externalReference` e `email`;
   - se não encontrar, cria novo cliente;
   - usa o `cus_...` resolvido para seguir com emissão.
3. Se não vier `customer` nem `customerData`:
   - payload segue sem cliente e a Asaas tende a rejeitar por validação.

## Campos aceitos em `customerData`

- `name`, `cpfCnpj`, `email`, `phone`, `mobilePhone`
- `postalCode`, `address`, `addressNumber`, `complement`, `province`
- `externalReference`, `company`, `additionalEmails`
- `municipalInscription`, `stateInscription`, `observations`, `groupName`, `notificationDisabled`
