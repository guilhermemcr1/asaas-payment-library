<?php

declare(strict_types=1);

namespace AsaasBiblioteca\DTO;

final class GatewayResponse
{
    public static function success(string $message, array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    public static function error(string $message, string $errorCode = 'integrationError', array $data = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'errorCode' => $errorCode,
            'data' => $data,
        ];
    }
}
