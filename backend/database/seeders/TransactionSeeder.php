<?php

namespace Database\Seeders;

use App\Models\Transaction;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Seed realistic unreviewed (and one reviewed) transactions.
     */
    public function run(): void
    {
        $transactions = [
            [
                'merchant' => 'HEB',
                'amount' => '84.23',
                'category' => null,
                'transaction_date' => '2026-07-10',
                'reviewed_at' => null,
            ],
            [
                'merchant' => 'Shell Gas',
                'amount' => '42.50',
                'category' => null,
                'transaction_date' => '2026-07-12',
                'reviewed_at' => null,
            ],
            [
                'merchant' => 'Netflix',
                'amount' => '15.99',
                'category' => null,
                'transaction_date' => '2026-07-14',
                'reviewed_at' => null,
            ],
            [
                'merchant' => 'Chipotle',
                'amount' => '13.45',
                'category' => null,
                'transaction_date' => '2026-07-16',
                'reviewed_at' => null,
            ],
            [
                'merchant' => 'Capital One Payment',
                'amount' => '200.00',
                'category' => null,
                'transaction_date' => '2026-07-18',
                'reviewed_at' => null,
            ],
            [
                // Included so you can verify "unreviewed only" filtering later.
                'merchant' => 'Already Reviewed Coffee',
                'amount' => '5.75',
                'category' => 'want',
                'transaction_date' => '2026-07-08',
                'reviewed_at' => '2026-07-09 10:00:00',
            ],
        ];

        foreach ($transactions as $transaction) {
            // forceFill bypasses $fillable so seeding works while you learn mass assignment.
            (new Transaction)->forceFill($transaction)->save();
        }
    }
}
