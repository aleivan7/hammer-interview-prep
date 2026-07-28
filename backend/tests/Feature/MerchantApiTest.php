<?php

namespace Tests\Feature;

use App\Enums\MatchStrategy;
use App\Models\Merchant;
use App\Models\MerchantAlias;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class MerchantApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->withDemoUser();
    }

    #[TestDox('Lists canonical merchants with representative descriptor aliases')]
    public function test_lists_merchants_with_example_descriptors(): void
    {
        $this->seed(CatalogSeeder::class);

        $response = $this->getJson('/api/merchants');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'name',
                        'normalized_name',
                        'logo_key',
                        'example_descriptors',
                    ],
                ],
            ]);

        $netflix = collect($response->json('data'))->firstWhere('name', 'Netflix');

        $this->assertNotNull($netflix);
        $this->assertSame('netflix', $netflix['normalized_name']);
        $this->assertNotEmpty($netflix['example_descriptors']);
        $this->assertTrue(
            collect($netflix['example_descriptors'])->contains(
                fn (array $alias): bool => $alias['pattern'] === 'NETFLIX'
                    && $alias['match_strategy'] === MatchStrategy::Exact->value,
            ),
        );
    }

    #[TestDox('Merchant search matches canonical names and alias patterns')]
    public function test_merchant_search_matches_name_and_aliases(): void
    {
        $this->seed(CatalogSeeder::class);

        $byName = $this->getJson('/api/merchants?search=netflix');
        $byName->assertOk();
        $this->assertTrue(
            collect($byName->json('data'))->contains(fn (array $row): bool => $row['name'] === 'Netflix'),
        );

        $byAlias = $this->getJson('/api/merchants?search=SPOTIFY%20USA');
        $byAlias->assertOk();
        $this->assertTrue(
            collect($byAlias->json('data'))->contains(fn (array $row): bool => $row['name'] === 'Spotify'),
        );
    }

    #[TestDox('Merchant seeds create unique normalized names and reusable aliases')]
    public function test_merchant_seeds_are_idempotent_and_unique(): void
    {
        $this->seed(CatalogSeeder::class);
        $merchantCount = Merchant::query()->count();
        $aliasCount = MerchantAlias::query()->count();

        $this->seed(CatalogSeeder::class);

        $this->assertSame($merchantCount, Merchant::query()->count());
        $this->assertSame($aliasCount, MerchantAlias::query()->count());
        $this->assertDatabaseHas('merchants', [
            'normalized_name' => 'shell',
            'name' => 'Shell',
        ]);
        $this->assertDatabaseHas('merchant_aliases', [
            'normalized_pattern' => 'SHELL',
            'match_strategy' => MatchStrategy::Exact->value,
            'enabled' => true,
        ]);
    }

    #[TestDox('Merchant list requires a selected demo user')]
    public function test_merchant_list_requires_demo_user(): void
    {
        $this->flushHeaders();

        $this->getJson('/api/merchants')->assertUnauthorized();
    }
}
