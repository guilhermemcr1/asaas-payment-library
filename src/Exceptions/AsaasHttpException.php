<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Exceptions;

class AsaasHttpException extends AsaasException
{
    private int $statusCode;
    private array $responseData;

    public function __construct(string $message, int $statusCode = 0, array $responseData = [])
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->responseData = $responseData;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseData(): array
    {
        return $this->responseData;
    }
}
