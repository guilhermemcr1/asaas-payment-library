<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Config;

final class AsaasConfig
{
    private array $config;
    private array $options;
    private array $featureConfigCache = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->options = $this->loadOptions();
    }

    public function getApiBaseUrl(): string
    {
        $baseUrl = $this->config['api_base_url'] ?? getenv('ASAAS_API_BASE_URL') ?: '';
        if ($baseUrl !== '') {
            return rtrim((string) $baseUrl, '/');
        }

        $optionProd = trim((string) ($this->options['api']['base_url_prod'] ?? ''));
        $optionSandbox = trim((string) ($this->options['api']['base_url_sandbox'] ?? ''));
        $isSandbox = $this->isSandbox();
        if ($isSandbox) {
            if ($optionSandbox !== '') {
                return rtrim($optionSandbox, '/');
            }
            return 'https://sandbox.asaas.com/api/v3';
        }

        if ($optionProd !== '') {
            return rtrim($optionProd, '/');
        }

        return 'https://api.asaas.com/v3';
    }

    public function getApiKey(): string
    {
        $explicit = trim((string) ($this->config['api_key'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $fromEnv = trim((string) (getenv('ASAAS_API_KEY') ?: ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        if ($this->isSandbox()) {
            return trim((string) ($this->options['api']['api_key_sandbox'] ?? ''));
        }

        return trim((string) ($this->options['api']['api_key_prod'] ?? ''));
    }

    public function getWebhookToken(): string
    {
        $explicit = trim((string) ($this->config['webhook_token'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $fromEnv = trim((string) (getenv('ASAAS_WEBHOOK_TOKEN') ?: ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        if ($this->isSandbox()) {
            return trim((string) ($this->options['webhook']['token_sandbox'] ?? ''));
        }

        return trim((string) ($this->options['webhook']['token_prod'] ?? ''));
    }

    public function getWebhookTokenHeader(): string
    {
        $header = trim((string) ($this->config['webhook_token_header'] ?? ''));
        if ($header !== '') {
            return strtolower($header);
        }

        $headerOption = trim((string) ($this->options['webhook']['token_header'] ?? ''));
        if ($headerOption !== '') {
            return strtolower($headerOption);
        }

        return 'x-webhook-token';
    }

    public function getWebhookAllowedIps(): array
    {
        $ips = $this->config['webhook_allowed_ips'] ?? [];
        if (is_array($ips) && $ips !== []) {
            return array_values(array_unique(array_map('strval', $ips)));
        }

        $optionIps = $this->options['webhook']['allowed_ips'] ?? [];
        if (is_array($optionIps) && $optionIps !== []) {
            return array_values(array_unique(array_map('strval', $optionIps)));
        }

        $envIps = trim((string) (getenv('ASAAS_WEBHOOK_ALLOWED_IPS') ?: ''));
        if ($envIps === '') {
            return [];
        }

        $rawList = array_map('trim', explode(',', $envIps));
        $filtered = array_filter($rawList, static fn (string $ip): bool => $ip !== '');
        return array_values(array_unique($filtered));
    }

    public function isWebhookIpFilterEnabled(): bool
    {
        if (array_key_exists('webhook_ip_filter_enabled', $this->config)) {
            return (bool) $this->config['webhook_ip_filter_enabled'];
        }

        $envValue = strtolower(trim((string) (getenv('ASAAS_WEBHOOK_IP_FILTER_ENABLED') ?: '')));
        if ($envValue !== '') {
            return in_array($envValue, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) ($this->options['webhook']['ip_filter_enabled'] ?? true);
    }

    public function getTimeoutSeconds(): int
    {
        $value = (int) ($this->config['timeout_seconds'] ?? ($this->options['api']['timeout_seconds'] ?? 15));
        if ($value < 5) {
            return 5;
        }

        return $value;
    }

    public function getRetryAttempts(): int
    {
        $value = (int) ($this->config['retry_attempts'] ?? ($this->options['api']['retry_attempts'] ?? 1));
        if ($value < 0) {
            return 0;
        }

        if ($value > 3) {
            return 3;
        }

        return $value;
    }

    public function getApiUserAgent(): string
    {
        $explicit = trim((string) ($this->config['api_user_agent'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $envUserAgent = trim((string) (getenv('ASAAS_API_USER_AGENT') ?: ''));
        if ($envUserAgent !== '') {
            return $envUserAgent;
        }

        $legacyKey = $this->isSandbox() ? 'user_agent_sandbox' : 'user_agent_prod';
        $legacyAgent = trim((string) ($this->options['api'][$legacyKey] ?? ''));
        if ($legacyAgent !== '') {
            return $legacyAgent;
        }

        $baseAgent = trim((string) ($this->options['api']['user_agent_base'] ?? ''));
        if ($baseAgent === '') {
            $baseAgent = 'AsaasLibrary/1.0';
        }

        $host = function_exists('asaasObterHostAtual') ? asaasObterHostAtual() : '';
        if ($host === '') {
            $host = 'host-indefinido';
        }

        return $baseAgent . ' (' . $this->getEnvironmentLabel() . '; ' . $host . ')';
    }

    public function getEnvironmentLabel(): string
    {
        return $this->isSandbox() ? 'sandbox' : 'production';
    }

    public function isSandbox(): bool
    {
        if (array_key_exists('sandbox', $this->config) && !array_key_exists('environment', $this->config)) {
            return (bool) $this->config['sandbox'];
        }

        $explicitEnvironment = strtolower(trim((string) ($this->config['environment'] ?? '')));
        if ($explicitEnvironment !== '') {
            return $this->resolveSandboxFromEnvironmentSetting($explicitEnvironment);
        }

        $envValue = strtolower(trim((string) (getenv('ASAAS_ENV') ?: '')));
        if ($envValue !== '') {
            return $this->resolveSandboxFromEnvironmentSetting($envValue);
        }

        $optionsEnvironment = strtolower(trim((string) ($this->options['environment'] ?? '')));
        if ($optionsEnvironment !== '') {
            return $this->resolveSandboxFromEnvironmentSetting($optionsEnvironment);
        }

        if (array_key_exists('sandbox', $this->options)) {
            return (bool) $this->options['sandbox'];
        }

        return true;
    }

    private function resolveSandboxFromEnvironmentSetting(string $environment): bool
    {
        if (function_exists('asaasResolverSandboxPorAmbiente')) {
            $prodHosts = $this->options['prod_hosts'] ?? [];
            $devHosts = $this->options['dev_hosts'] ?? [];
            if (!is_array($prodHosts)) {
                $prodHosts = [];
            }
            if (!is_array($devHosts)) {
                $devHosts = [];
            }

            return asaasResolverSandboxPorAmbiente($environment, $prodHosts, $devHosts);
        }

        $mode = strtolower(trim($environment));
        if ($mode === 'production' || $mode === 'prod') {
            return false;
        }

        if ($mode === 'sandbox' || $mode === 'dev') {
            return true;
        }

        return true;
    }

    public function getDbConfig(): array
    {
        if ($this->isSandbox()) {
            return is_array($this->options['db']['sandbox'] ?? null) ? $this->options['db']['sandbox'] : [];
        }

        return is_array($this->options['db']['prod'] ?? null) ? $this->options['db']['prod'] : [];
    }

    public function getInternalToken(): string
    {
        $explicit = trim((string) ($this->config['internal_token'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }
        return trim((string) ($this->options['internal']['token'] ?? ''));
    }

    public function getInternalHmacSecret(): string
    {
        $explicit = trim((string) ($this->config['internal_hmac_secret'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }
        return trim((string) ($this->options['internal']['hmac_secret'] ?? ''));
    }

    public function getInternalTimestampWindowSeconds(): int
    {
        return max(60, (int) ($this->options['internal']['timestamp_window_seconds'] ?? 300));
    }

    public function getInternalAllowedIps(): array
    {
        $ips = $this->options['internal']['allowed_ips'] ?? [];
        if (!is_array($ips)) {
            return [];
        }

        return array_values(array_unique(array_map('strval', $ips)));
    }

    public function isInternalHttpApiEnabled(): bool
    {
        if (array_key_exists('internal_http_api_enabled', $this->config)) {
            return (bool) $this->config['internal_http_api_enabled'];
        }

        $envValue = strtolower(trim((string) (getenv('ASAAS_INTERNAL_HTTP_API_ENABLED') ?: '')));
        if ($envValue !== '') {
            if (in_array($envValue, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }

            return in_array($envValue, ['1', 'true', 'yes', 'on'], true);
        }

        if (array_key_exists('http_api_enabled', $this->options['internal'] ?? [])) {
            return (bool) $this->options['internal']['http_api_enabled'];
        }

        return true;
    }

    public function getPaymentLinkDefaultEndDateDays(): int
    {
        $explicit = $this->config['payment_link_default_end_date_days'] ?? null;
        if (is_numeric($explicit)) {
            return max(0, (int) $explicit);
        }

        $cardLinkDefaults = $this->getPaymentCardLinkDefaults();
        if (isset($cardLinkDefaults['defaultEndDateDays']) && is_numeric($cardLinkDefaults['defaultEndDateDays'])) {
            return max(0, (int) $cardLinkDefaults['defaultEndDateDays']);
        }

        return 1;
    }

    public function isDebugEnabled(): bool
    {
        if (array_key_exists('debug_enabled', $this->config)) {
            return (bool) $this->config['debug_enabled'];
        }

        $envDebug = strtolower(trim((string) (getenv('ASAAS_DEBUG') ?: '')));
        if ($envDebug !== '') {
            return in_array($envDebug, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) ($this->options['debug']['enabled'] ?? false);
    }

    public function isDebugSafeDetailsEnabled(): bool
    {
        if (array_key_exists('debug_safe_details', $this->config)) {
            return (bool) $this->config['debug_safe_details'];
        }

        return (bool) ($this->options['debug']['safe_details'] ?? true);
    }

    public function getFeatureConfig(string $featureKey): array
    {
        if (isset($this->featureConfigCache[$featureKey])) {
            return $this->featureConfigCache[$featureKey];
        }

        $baseDir = dirname(__DIR__, 2);
        if (function_exists('asaasCarregarFeatureConfig')) {
            $config = asaasCarregarFeatureConfig($baseDir, $featureKey);
            if (is_array($config) && $config !== []) {
                $this->featureConfigCache[$featureKey] = $config;
                return $config;
            }
        }

        $configPath = $baseDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $featureKey . '.php';
        if (!is_file($configPath)) {
            $this->featureConfigCache[$featureKey] = [];
            return [];
        }

        $loaded = include $configPath;
        $config = is_array($loaded) ? $loaded : [];
        $this->featureConfigCache[$featureKey] = $config;

        return $config;
    }

    public function getFeatureDefaults(string $featureKey): array
    {
        $overrideKey = $this->resolveFeatureDefaultsOverrideKey($featureKey);
        if ($overrideKey !== null && array_key_exists($overrideKey, $this->config)) {
            $defaults = $this->config[$overrideKey];
            return is_array($defaults) ? $defaults : [];
        }

        $featureConfig = $this->getFeatureConfig($featureKey);
        $defaults = $featureConfig['defaults'] ?? [];
        if (!is_array($defaults)) {
            $defaults = [];
        }

        foreach (['fine', 'interest', 'discount'] as $block) {
            if (!isset($defaults[$block]) && isset($featureConfig[$block]) && is_array($featureConfig[$block])) {
                $defaults[$block] = $featureConfig[$block];
            }
        }

        return $defaults;
    }

    public function getPaymentPixDefaults(): array
    {
        return $this->getFeatureDefaults('create_payment_pix');
    }

    public function getPaymentBilletDefaults(): array
    {
        return $this->getFeatureDefaults('create_payment_billet');
    }

    public function getPaymentCardLinkDefaults(): array
    {
        return $this->getFeatureDefaults('create_payment_card_link');
    }

    public function getSubscriptionDefaults(): array
    {
        return $this->getFeatureDefaults('create_subscription');
    }

    public function getCustomerResolutionDefaults(): array
    {
        return $this->getFeatureDefaults('cliente_resolucao_automatica');
    }

    public function getListagensDefaultLimit(): int
    {
        $defaults = $this->getFeatureDefaults('listagens');
        $limit = (int) ($defaults['defaultLimit'] ?? 100);
        return max(1, $limit);
    }

    public function getDefaultPaymentMethod(): string
    {
        $defaults = $this->getFeatureDefaults('http_actions');
        $method = strtolower(trim((string) ($defaults['defaultPaymentMethod'] ?? 'pix')));
        return $method !== '' ? $method : 'pix';
    }

    public function getInvoiceDefaults(): array
    {
        return $this->getFeatureDefaults('issue_invoice');
    }

    public function getInvoiceIssueNowDefault(): bool
    {
        if (array_key_exists('invoice_issue_now', $this->config)) {
            return (bool) $this->config['invoice_issue_now'];
        }

        $featureConfig = $this->getFeatureConfig('issue_invoice');
        if (array_key_exists('issueNow', $featureConfig)) {
            return (bool) $featureConfig['issueNow'];
        }

        return true;
    }

    private function resolveFeatureDefaultsOverrideKey(string $featureKey): ?string
    {
        if ($featureKey === 'issue_invoice' && array_key_exists('invoice_defaults', $this->config)) {
            return 'invoice_defaults';
        }

        $configKey = $featureKey . '_defaults';
        if (array_key_exists($configKey, $this->config)) {
            return $configKey;
        }

        return null;
    }

    private function loadOptions(): array
    {
        $baseDir = dirname(__DIR__, 2);
        $helpersPath = $baseDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'helpers.php';
        if (is_file($helpersPath)) {
            require_once $helpersPath;
        }

        if (function_exists('asaasCarregarOptions')) {
            $options = asaasCarregarOptions($baseDir);
            if (is_array($options)) {
                return $options;
            }
        }

        $optionsPath = $baseDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'options.php';
        if (is_file($optionsPath)) {
            $loaded = include $optionsPath;
            if (is_array($loaded)) {
                return $loaded;
            }
        }

        return [];
    }
}
