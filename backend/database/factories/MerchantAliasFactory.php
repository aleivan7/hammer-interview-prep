<?php

namespace Database\Factories;

use App\Enums\MatchStrategy;
use App\Models\Merchant;
use App\Models\MerchantAlias;
use App\Support\CatalogNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantAlias>
 */
class MerchantAliasFactory extends Factory
{
    protected $model = MerchantAlias::class;

    public function definition(): array
    {
        $pattern = fake()->unique()->lexify('????-####');

        return [
            'merchant_id' => Merchant::factory(),
            'pattern' => $pattern,
            'normalized_pattern' => CatalogNormalizer::descriptor($pattern),
            'match_strategy' => MatchStrategy::Exact,
            'priority' => 100,
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'enabled' => false,
        ]);
    }
}
