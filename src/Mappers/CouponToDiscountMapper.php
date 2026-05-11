<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Mappers;

final class CouponToDiscountMapper
{
    public function map(array $payload): ?array
    {
        if (isset($payload['discount']) && is_array($payload['discount'])) {
            return $this->normalizeDiscount($payload['discount']);
        }

        $couponType = strtoupper(trim((string) ($payload['couponType'] ?? $payload['coupon_type'] ?? '')));
        $couponValue = $payload['couponValue'] ?? $payload['coupon_value'] ?? null;
        if (!is_numeric($couponValue) || (float) $couponValue <= 0) {
            return null;
        }

        if ($couponType !== 'PERCENTAGE' && $couponType !== 'FIXED') {
            $couponType = 'PERCENTAGE';
        }

        $dueDateLimitDays = $payload['couponDueDateLimitDays'] ?? $payload['coupon_due_date_limit_days'] ?? 0;
        $dueDateLimitDaysInt = max(0, (int) $dueDateLimitDays);

        return [
            'value' => (float) $couponValue,
            'type' => $couponType,
            'dueDateLimitDays' => $dueDateLimitDaysInt,
        ];
    }

    private function normalizeDiscount(array $discount): ?array
    {
        $value = $discount['value'] ?? null;
        if (!is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        $type = strtoupper(trim((string) ($discount['type'] ?? 'PERCENTAGE')));
        if ($type !== 'PERCENTAGE' && $type !== 'FIXED') {
            $type = 'PERCENTAGE';
        }

        $dueDateLimitDays = max(0, (int) ($discount['dueDateLimitDays'] ?? 0));

        return [
            'value' => (float) $value,
            'type' => $type,
            'dueDateLimitDays' => $dueDateLimitDays,
        ];
    }
}
