<?php

namespace App\Enums;

enum MatchStrategy: string
{
    case Exact = 'exact';
    case Prefix = 'prefix';
    case WholeToken = 'whole_token';
    case SafeContains = 'safe_contains';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
