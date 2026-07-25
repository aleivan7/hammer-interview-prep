<?php

namespace App\Enums;

enum AccountSyncStatus: string
{
    case Healthy = 'healthy';
    case Error = 'error';
    case Pending = 'pending';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
