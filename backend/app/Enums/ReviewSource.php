<?php

namespace App\Enums;

enum ReviewSource: string
{
    case Manual = 'manual';
    case Rule = 'rule';
    case Heuristic = 'heuristic';
    case Undo = 'undo';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
