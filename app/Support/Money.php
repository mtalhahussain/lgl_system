<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function minor(string|int $amount): int
    {
        $amount = trim((string) $amount);
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $amount)) {
            throw new InvalidArgumentException('Amount must be nonnegative with at most two decimal places.');
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $minor): string
    {
        if ($minor < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
