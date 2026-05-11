<?php

declare(strict_types=1);

use AsaasBiblioteca\AsaasGateway;
use AsaasBiblioteca\Config\AsaasConfig;
use AsaasBiblioteca\DTO\GatewayResponse;
use AsaasBiblioteca\Exceptions\AsaasException;
use AsaasBiblioteca\Exceptions\AsaasHttpException;
use AsaasBiblioteca\Http\ActionRouter;
use AsaasBiblioteca\Security\InternalAuthGuard;

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function asaasReadHeaders(): array
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            return $headers;
        }
    }

    $result = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') !== 0) {
            continue;
        }
        $name = strtolower(str_replace('_', '-', substr($key, 5)));
        $result[$name] = (string) $value;
    }
    return $result;
}

$rawBody = (string) file_get_contents('php://input');
$decoded = json_decode($rawBody, true);
$payload = is_array($decoded) ? $decoded : [];
$action = strtolower(trim((string) ($payload['action'] ?? '')));
if ($action === '') {
    $action = 'webhook_receive';
}

$headers = asaasReadHeaders();
$remoteIp = (string) ($_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
$httpMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'POST'));

$config = new AsaasConfig();
$internalAuth = new InternalAuthGuard($config);
$isDebugEnabled = $config->isDebugEnabled();
$isDebugSafe = $config->isDebugSafeDetailsEnabled();
$environmentLabel = $config->isSandbox() ? 'sandbox' : 'production';

$buildDebugContext = static function (int $statusCode, ?string $exceptionClass = null) use ($isDebugEnabled, $isDebugSafe, $environmentLabel, $action, $httpMethod): array {
    if (!$isDebugEnabled || !$isDebugSafe) {
        return [];
    }

    $context = [
        'environment' => $environmentLabel,
        'action' => $action,
        'httpMethod' => $httpMethod,
        'statusCode' => $statusCode,
    ];
    if ($exceptionClass !== null && $exceptionClass !== '') {
        $context['exception'] = $exceptionClass;
    }

    return ['debug' => $context];
};

if ($action !== 'webhook_receive' && !$config->isInternalHttpApiEnabled()) {
    http_response_code(403);
    echo json_encode(
        GatewayResponse::error('Internal HTTP API is disabled. Use AsaasGateway in-process.', 'httpApiDisabled'),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($action !== 'webhook_receive') {
    $auth = $internalAuth->validate($headers, $rawBody, $remoteIp);
    if (empty($auth['ok'])) {
        http_response_code((int) ($auth['statusCode'] ?? 403));
        echo json_encode(
            GatewayResponse::error((string) ($auth['message'] ?? 'Access denied.'), (string) ($auth['errorCode'] ?? 'forbidden')),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
}

try {
    $gateway = new AsaasGateway();
    $router = new ActionRouter($gateway, $config);
    $result = $router->handle($action, $payload, $headers, $remoteIp, $rawBody, $httpMethod);
    http_response_code((int) ($result['status_code'] ?? 200));
    echo json_encode($result['payload'] ?? GatewayResponse::error('Resposta inválida.', 'invalid_response'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (AsaasHttpException $e) {
    $statusCode = $e->getStatusCode();
    if ($statusCode < 400 || $statusCode > 599) {
        $statusCode = 502;
    }
    http_response_code($statusCode);
    $errorData = $buildDebugContext($statusCode, get_class($e));
    if ($isDebugEnabled && $isDebugSafe) {
        $errorData['message'] = $e->getMessage();
        $errorData['statusCode'] = $e->getStatusCode();
        $errorData['response'] = $e->getResponseData();
    }
    echo json_encode(
        GatewayResponse::error('Erro na API Asaas.', 'gatewayError', $errorData),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (AsaasException $e) {
    $statusCode = 502;
    http_response_code($statusCode);
    $errorData = $buildDebugContext($statusCode, get_class($e));
    if ($isDebugEnabled && $isDebugSafe) {
        $errorData['message'] = $e->getMessage();
    }
    echo json_encode(
        GatewayResponse::error('Falha de comunicação com a Asaas.', 'integrationError', $errorData),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    $statusCode = 500;
    http_response_code($statusCode);
    $errorData = $buildDebugContext($statusCode, get_class($e));
    if ($isDebugEnabled && $isDebugSafe) {
        $errorData['message'] = $e->getMessage();
    }
    echo json_encode(
        GatewayResponse::error('Erro interno na biblioteca Asaas.', 'internalError', $errorData),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}
