<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Support\CatalogNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    protected $model = Merchant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'normalized_name' => CatalogNormalizer::name($name),
            'logo_key' => null,
        ];
    }
}
