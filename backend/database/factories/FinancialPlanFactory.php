<?php

namespace Database\Factories;

use App\Models\FinancialPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialPlan>
 */
class FinancialPlanFactory extends Factory
{
    protected $model = FinancialPlan::class;

    public function definition(): array
    {
        return [
            'needs_percent' => 50,
            'wants_percent' => 30,
            'savings_percent' => 20,
            'safety_buffer_cents' => 25_000,
            'monthly_income_cents' => 520_000,
        ];
    }
}
