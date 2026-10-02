<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use OverflowException;

final class Money
{
    public static function cents(string $amount): int
    {
        if (PHP_INT_SIZE !== 8 || ! preg_match('/\A([0-9]{1,10})(?:\.([0-9]{1,2}))?\z/D', $amount, $parts)) {
            throw new InvalidArgumentException('Use up to ten whole digits and at most two decimal places.');
        }

        return ((int) $parts[1]) * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function add(int $a, int $b): int
    {
        if ($b > 0 && $a > PHP_INT_MAX - $b) {
            throw new OverflowException('Money aggregate exceeds the supported integer range.');
        }

        return $a + $b;
    }

    /** @return array<string, int> */
    public static function equal(int $total, array $ids): array
    {
        if ($total < 1 || count($ids) === 0 || count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException('A positive amount and unique participants are required.');
        }
        sort($ids, SORT_STRING);
        $shares = [];
        foreach ($ids as $index => $id) {
            $shares[$id] = intdiv($total, count($ids)) + ($index < $total % count($ids) ? 1 : 0);
        }

        return $shares;
    }
}
