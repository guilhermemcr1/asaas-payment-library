<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Support;

final class FeaturePayloadMerger
{
    public static function merge(array $defaults, array $payload): array
    {
        $merged = $defaults;
        foreach ($payload as $key => $value) {
            if ($value === null) {
                continue;
            }
            $merged[$key] = $value;
        }

        return $merged;
    }
}
