<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Infrastructure;

use AsaasBiblioteca\Config\AsaasConfig;
use PDO;

final class PdoConnectionFactory
{
    private AsaasConfig $config;
    private ?PDO $pdo = null;

    public function __construct(AsaasConfig $config)
    {
        $this->config = $config;
    }

    public function getConnection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $db = $this->config->getDbConfig();
        $host = (string) ($db['host'] ?? '127.0.0.1');
        $port = (int) ($db['port'] ?? 3306);
        $name = (string) ($db['name'] ?? '');
        $user = (string) ($db['user'] ?? '');
        $pass = (string) ($db['pass'] ?? '');
        $charset = (string) ($db['charset'] ?? 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo = $pdo;
        return $this->pdo;
    }
}
