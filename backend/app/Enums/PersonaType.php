<?php

namespace App\Enums;

enum PersonaType: string
{
    case Reckless = 'reckless';
    case Average = 'average';
    case HighNetWorth = 'high_net_worth';

    public function label(): string
    {
        return match ($this) {
            self::Reckless => 'Reckless Spender',
            self::Average => 'Average Spender',
            self::HighNetWorth => 'High-Net-Worth Individual',
        };
    }

    public function financialStatusLabel(): string
    {
        return match ($this) {
            self::Reckless => 'Under financial pressure',
            self::Average => 'Balanced and on track',
            self::HighNetWorth => 'Strong savings progress',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
