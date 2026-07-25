<?php

namespace Database\Factories;

use App\Enums\AccountSyncStatus;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'institution_name' => fake()->company(),
            'name' => 'Checking',
            'mask' => (string) fake()->numerify('####'),
            'type' => 'checking',
            'balance_cents' => fake()->numberBetween(10_000, 500_000),
            'sync_status' => AccountSyncStatus::Healthy,
            'logo_key' => 'generic',
            'sort_order' => 0,
        ];
    }
}
