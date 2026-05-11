<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Support;

final class PaginationHelper
{
    public static function build(array $filters, int $defaultLimit = 100): array
    {
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;
        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : $defaultLimit;

        return [
            'offset' => $offset,
            'limit' => $limit,
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{offset:int, limit:int, hasMore:bool}
     */
    public static function fromRaw(array $raw, int $offset, int $limit): array
    {
        $items = $raw['data'] ?? [];
        $itemCount = is_array($items) ? count($items) : 0;
        $hasMore = $itemCount >= $limit;
        if (isset($raw['hasMore'])) {
            $hasMore = (bool) $raw['hasMore'];
        }

        return [
            'offset' => $offset,
            'limit' => $limit,
            'hasMore' => $hasMore,
        ];
    }
}
