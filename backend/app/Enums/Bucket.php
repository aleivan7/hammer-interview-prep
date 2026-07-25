<?php

namespace App\Enums;

enum Bucket: string
{
    case Need = 'need';
    case Want = 'want';
    case Savings = 'savings';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
