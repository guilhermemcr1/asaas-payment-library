<?php

$hostsProducaoAsaas = [
    'seu-sistema.com.br',
    'www.seu-sistema.com.br',
];

$hostsDevAsaas = [
    'dev.seu-sistema.com.br',
    'www.dev.seu-sistema.com.br',
    'localhost',
    '127.0.0.1',
];

$helpers = __DIR__ . DIRECTORY_SEPARATOR . 'helpers.php';
if (is_file($helpers)) {
    require_once $helpers;
}

return [
    'prod_hosts' => $hostsProducaoAsaas,
    'dev_hosts' => $hostsDevAsaas,
    'environment' => 'auto',
    'api' => [
        'base_url_prod' => 'https://api.asaas.com/v3',
        'base_url_sandbox' => 'https://sandbox.asaas.com/api/v3',
        'api_key_prod' => 'COLE_A_API_KEY_PRODUCAO_AQUI',
        'api_key_sandbox' => 'COLE_A_API_KEY_SANDBOX_AQUI',
        'user_agent_base' => 'AsaasLibrary/1.0',
        'timeout_seconds' => 20,
        'retry_attempts' => 1,
    ],
    'debug' => [
        'enabled' => false,
        'safe_details' => true,
    ],
    'db' => [
        'prod' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'COLE_DB_PROD_AQUI',
            'user' => 'COLE_DB_USER_PROD_AQUI',
            'pass' => 'COLE_DB_PASS_PROD_AQUI',
            'charset' => 'utf8mb4',
        ],
        'sandbox' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'COLE_DB_NAME_SANDBOX_AQUI',
            'user' => 'COLE_DB_USER_SANDBOX_AQUI',
            'pass' => 'COLE_DB_PASS_SANDBOX_AQUI',
            'charset' => 'utf8mb4',
        ],
    ],
    'internal' => [
        'http_api_enabled' => true,
        'token' => 'GERE_UM_TOKEN_UNICO_PARA_A_SUA_API_INTERNA',
        'hmac_secret' => 'GERE_UM_HMAC_SECRET_UNICO_PARA_A_SUA_API_INTERNA',
        'timestamp_window_seconds' => 300,
        'allowed_ips' => [
            '127.0.0.1',
            '192.168.1.1',
            '10.0.0.1',
        ],
    ],
    'webhook' => [
        'token_prod' => 'COLE_O_TOKEN_WEBHOOK_PROD_AQUI',
        'token_sandbox' => 'COLE_O_TOKEN_WEBHOOK_SANDBOX_AQUI',
        'token_header' => 'x-webhook-token',
        'ip_filter_enabled' => false,
        'allowed_ips' => [
            '52.67.12.206',
            '18.230.8.159',
            '54.94.136.112',
            '54.94.183.101',
        ],
    ],
];
