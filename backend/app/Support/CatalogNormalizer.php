<?php

namespace App\Support;

final class CatalogNormalizer
{
    public static function name(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_strtolower($collapsed);
    }

    public static function descriptor(string $value): string
    {
        $upper = mb_strtoupper(trim($value));
        $normalized = preg_replace('/[^A-Z0-9]+/u', ' ', $upper) ?? '';
        $collapsed = preg_replace('/\s+/u', ' ', trim($normalized)) ?? '';

        return $collapsed;
    }
}
