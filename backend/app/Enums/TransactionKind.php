<?php

namespace App\Enums;

enum TransactionKind: string
{
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';
    case Refund = 'refund';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isSpending(): bool
    {
        return $this === self::Expense;
    }

    public function affectsSafeToSpendCash(): bool
    {
        return match ($this) {
            self::Expense, self::Income, self::Refund => true,
            self::Transfer => false,
        };
    }
}
