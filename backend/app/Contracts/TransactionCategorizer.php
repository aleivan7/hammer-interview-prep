<?php

namespace App\Contracts;

use App\Models\Transaction;
use App\Support\CategorizationResult;

interface TransactionCategorizer
{
    public function categorize(Transaction $transaction): CategorizationResult;
}
