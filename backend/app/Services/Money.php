<?php

namespace App\Services;

/**
 * Integer-cent money helpers. Never use floating-point for financial math.
 */
final class Money
{
    public static function dollarsToCents(string|int|float $dollars): int
    {
        if (is_int($dollars)) {
            return $dollars * 100;
        }

        $normalized = is_float($dollars)
            ? number_format($dollars, 2, '.', '')
            : trim((string) $dollars);

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $normalized)) {
            throw new \InvalidArgumentException("Invalid money amount: {$normalized}");
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    public static function centsToDollarString(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $whole = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        return sprintf('%s%d.%02d', $negative ? '-' : '', $whole, $fraction);
    }

    public static function sumCents(int ...$amounts): int
    {
        $total = 0;

        foreach ($amounts as $amount) {
            $total += $amount;
        }

        return $total;
    }

    public static function percentOf(int $partCents, int $wholeCents): int
    {
        if ($wholeCents === 0) {
            return 0;
        }

        return (int) round(($partCents * 100) / $wholeCents);
    }
}
