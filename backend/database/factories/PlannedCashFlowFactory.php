<?php

namespace Database\Factories;

use App\Enums\Bucket;
use App\Models\PlannedCashFlow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlannedCashFlow>
 */
class PlannedCashFlowFactory extends Factory
{
    protected $model = PlannedCashFlow::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Paycheck',
            'amount_cents' => 260_000,
            'kind' => 'income',
            'due_on' => now()->toDateString(),
            'is_essential' => false,
            'bucket' => null,
        ];
    }

    public function bill(): static
    {
        return $this->state(fn () => [
            'kind' => 'bill',
            'is_essential' => true,
            'bucket' => Bucket::Need,
        ]);
    }
}
