<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use AsaasBiblioteca\Config\AsaasConfig;
use AsaasBiblioteca\Security\InternalAuthGuard;

function assertTrue(bool $condition, string $message): void
{
    if ($condition) {
        echo "[OK] {$message}\n";
        return;
    }
    echo "[FAIL] {$message}\n";
    exit(1);
}

$config = new AsaasConfig([
    'environment' => 'sandbox',
    'internal_token' => 'token123',
    'internal_hmac_secret' => 'secret123',
]);

$guard = new InternalAuthGuard($config);
$body = json_encode(['action' => 'create_payment', 'value' => 10], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$timestamp = (string) time();
$signature = hash_hmac('sha256', $timestamp . '.' . $body, 'secret123');

$valid = $guard->validate([
    'x-internal-token' => 'token123',
    'x-timestamp' => $timestamp,
    'x-signature' => $signature,
], (string) $body, '127.0.0.1');

assertTrue(!empty($valid['ok']), 'Auth interna valida');

$invalid = $guard->validate([
    'x-internal-token' => 'token123',
    'x-timestamp' => $timestamp,
    'x-signature' => 'assinatura_invalida',
], (string) $body, '127.0.0.1');

assertTrue(empty($invalid['ok']), 'Auth interna invalida assinatura incorreta');

echo "Teste de seguranca validado.\n";
