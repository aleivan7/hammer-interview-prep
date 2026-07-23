<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant' => fake()->randomElement([
                'HEB',
                'Shell',
                'Netflix',
                'Spotify',
                'Amazon',
                'Chipotle',
                'Capital One Payment',
            ]),
            'amount' => fake()->randomFloat(2, 5, 250),
            'category' => null,
            'transaction_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'reviewed_at' => null,
        ];
    }

    /**
     * Mark the transaction as already reviewed.
     */
    public function reviewed(): static
    {
        return $this->state(fn () => [
            'category' => fake()->randomElement(['need', 'want', 'debt_savings']),
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Keep the transaction unreviewed.
     */
    public function unreviewed(): static
    {
        return $this->state(fn () => [
            'category' => null,
            'reviewed_at' => null,
        ]);
    }
}
