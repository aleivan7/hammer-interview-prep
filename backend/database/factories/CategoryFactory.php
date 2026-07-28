<?php

namespace Database\Factories;

use App\Enums\Bucket;
use App\Models\Category;
use App\Support\CatalogNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'user_id' => null,
            'bucket' => Bucket::Want,
            'name' => ucwords($name),
            'normalized_name' => CatalogNormalizer::name($name),
            'sort_order' => 100,
            'archived_at' => null,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'archived_at' => now(),
        ]);
    }
}
