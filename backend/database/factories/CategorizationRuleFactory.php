<?php

namespace Database\Factories;

use App\Enums\Bucket;
use App\Models\CategorizationRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategorizationRule>
 */
class CategorizationRuleFactory extends Factory
{
    protected $model = CategorizationRule::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Netflix is a want',
            'merchant_contains' => 'netflix',
            'account_id' => null,
            'amount_cents_min' => null,
            'amount_cents_max' => null,
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'entertainment',
            'priority' => 10,
            'enabled' => true,
            'auto_review' => true,
        ];
    }
}
