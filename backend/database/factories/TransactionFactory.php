<?php

namespace Database\Factories;

use App\Enums\Bucket;
use App\Enums\ReviewSource;
use App\Enums\TransactionKind;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => null,
            'merchant' => fake()->company(),
            'amount_cents' => fake()->numberBetween(500, 20_000),
            'kind' => TransactionKind::Expense,
            'bucket' => null,
            'subcategory' => null,
            'transaction_date' => fake()->dateTimeBetween('-2 weeks', 'now')->format('Y-m-d'),
            'reviewed_at' => null,
            'review_source' => null,
            'confidence' => null,
            'review_explanation' => null,
            'notes' => null,
            'idempotency_key' => null,
        ];
    }

    public function reviewed(): static
    {
        return $this->state(fn () => [
            'bucket' => Bucket::Need,
            'subcategory' => 'groceries',
            'reviewed_at' => now(),
            'review_source' => ReviewSource::Manual,
            'confidence' => 100,
        ]);
    }

    public function unreviewed(): static
    {
        return $this->state(fn () => [
            'bucket' => null,
            'subcategory' => null,
            'reviewed_at' => null,
            'review_source' => null,
            'confidence' => null,
            'review_explanation' => null,
            'idempotency_key' => null,
        ]);
    }
}
