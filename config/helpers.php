<?php

if (!function_exists('asaasObterHostAtual')) {
    function asaasObterHostAtual(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $host = strtolower(trim((string) $host));
        if (strpos($host, ':') !== false) {
            $host = explode(':', $host, 2)[0];
        }

        return $host;
    }
}

if (!function_exists('asaasSandboxAutomatico')) {
    function asaasSandboxAutomatico(array $hostsProducao, array $hostsDev = []): bool
    {
        $ambiente = asaasResolverAmbienteAutomatico($hostsProducao, $hostsDev);
        return $ambiente !== 'production';
    }
}

if (!function_exists('asaasResolverAmbienteAutomatico')) {
    function asaasResolverAmbienteAutomatico(array $hostsProducao, array $hostsDev = []): string
    {
        $host = asaasObterHostAtual();
        if ($host === '') {
            return 'sandbox';
        }

        foreach ($hostsProducao as $dominio) {
            $dominio = strtolower(trim((string) $dominio));
            if ($dominio !== '' && $host === $dominio) {
                return 'production';
            }
        }

        foreach ($hostsDev as $dominio) {
            $dominio = strtolower(trim((string) $dominio));
            if ($dominio !== '' && $host === $dominio) {
                return 'sandbox';
            }
        }

        error_log('[AsaasBiblioteca] Host nao mapeado em environment auto; usando sandbox: ' . $host);

        return 'sandbox';
    }
}

if (!function_exists('asaasResolverSandboxPorAmbiente')) {
    function asaasResolverSandboxPorAmbiente(string $environment, array $hostsProducao, array $hostsDev = []): bool
    {
        $mode = strtolower(trim($environment));
        if ($mode === 'production' || $mode === 'prod') {
            return false;
        }

        if ($mode === 'sandbox' || $mode === 'dev') {
            return true;
        }

        return asaasResolverAmbienteAutomatico($hostsProducao, $hostsDev) !== 'production';
    }
}

if (!function_exists('asaasResolverArquivoOptions')) {
    function asaasResolverArquivoOptions(string $baseDir): ?string
    {
        $candidatos = [
            $baseDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'options.php',
            $baseDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'options.php',
        ];

        foreach ($candidatos as $relativo) {
            $real = realpath($relativo);
            if ($real !== false && is_file($real)) {
                return $real;
            }
        }

        return null;
    }
}

if (!function_exists('asaasCarregarOptions')) {
    function asaasCarregarOptions(string $baseDir): array
    {
        $path = asaasResolverArquivoOptions($baseDir);
        if ($path === null) {
            return [];
        }

        $options = include $path;
        return is_array($options) ? $options : [];
    }
}

if (!function_exists('asaasCarregarFeatureConfig')) {
    function asaasCarregarFeatureConfig(string $baseDir, string $featureKey): array
    {
        if (!preg_match('/^[a-z0-9_]+$/', $featureKey)) {
            return [];
        }

        $fileName = $featureKey . '.php';
        $candidatos = [
            $baseDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $fileName,
            $baseDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $fileName,
        ];

        foreach ($candidatos as $relativo) {
            $real = realpath($relativo);
            if ($real === false || !is_file($real)) {
                continue;
            }

            $config = include $real;
            return is_array($config) ? $config : [];
        }

        return [];
    }
}

if (!function_exists('asaasCarregarIssueInvoiceConfig')) {
    function asaasCarregarIssueInvoiceConfig(string $baseDir): array
    {
        return asaasCarregarFeatureConfig($baseDir, 'issue_invoice');
    }
}
