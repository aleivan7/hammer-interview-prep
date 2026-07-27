<?php

namespace Tests\Unit;

use App\Enums\MatchStrategy;
use App\Models\Merchant;
use App\Models\MerchantAlias;
use App\Services\MerchantResolver;
use App\Support\CatalogNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class MerchantResolverTest extends TestCase
{
    use RefreshDatabase;

    private MerchantResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new MerchantResolver;
        $this->seedCatalogAliases();
    }

    #[TestDox('Resolves Netflix and Spotify descriptor examples with explanations')]
    #[DataProvider('positiveExamples')]
    public function test_resolves_required_positive_examples(
        string $descriptor,
        string $expectedMerchant,
        MatchStrategy $expectedStrategy,
    ): void {
        $result = $this->resolver->resolve($descriptor);

        $this->assertNotNull($result);
        $this->assertSame($expectedMerchant, $result->merchant->name);
        $this->assertSame($expectedStrategy, $result->strategy);
        $this->assertSame(CatalogNormalizer::descriptor($descriptor), $result->normalizedDescriptor);
        $this->assertStringContainsString($expectedMerchant, $result->explanation);
        $this->assertStringContainsString($expectedStrategy->value, $result->explanation);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: MatchStrategy}>
     */
    public static function positiveExamples(): array
    {
        return [
            'netflix hyphen city' => ['NETFLIX-NY', 'Netflix', MatchStrategy::Exact],
            'netflix domain suffix' => ['NETFLIX.COM 408724', 'Netflix', MatchStrategy::Prefix],
            'spotify trailing id' => ['spotify-94820349857', 'Spotify', MatchStrategy::Prefix],
            'spotify usa exact' => ['SPOTIFY USA', 'Spotify', MatchStrategy::Exact],
        ];
    }

    #[TestDox('Shellpoint Mortgage does not resolve to Shell without an intentional alias')]
    public function test_shellpoint_does_not_resolve_to_shell(): void
    {
        $this->assertNull($this->resolver->resolve('Shellpoint Mortgage'));
        $this->assertNull($this->resolver->resolve('SHELLPOINT MORTGAGE'));
    }

    #[TestDox('Disabled aliases are ignored')]
    public function test_disabled_aliases_are_ignored(): void
    {
        MerchantAlias::query()
            ->where('normalized_pattern', 'NETFLIX NY')
            ->where('match_strategy', MatchStrategy::Exact->value)
            ->update(['enabled' => false]);

        MerchantAlias::query()
            ->where('merchant_id', Merchant::query()->where('normalized_name', 'netflix')->value('id'))
            ->update(['enabled' => false]);

        $this->assertNull($this->resolver->resolve('NETFLIX-NY'));
    }

    #[TestDox('Unknown descriptors do not create merchants')]
    public function test_unknown_descriptors_do_not_create_merchants(): void
    {
        $before = Merchant::query()->count();

        $this->assertNull($this->resolver->resolve('Unknown Merchant 77'));
        $this->assertSame($before, Merchant::query()->count());
    }

    #[TestDox('More specific strategies win before lower priority')]
    public function test_strategy_specificity_beats_priority(): void
    {
        $merchant = Merchant::query()->where('normalized_name', 'netflix')->firstOrFail();

        MerchantAlias::query()->create([
            'merchant_id' => $merchant->id,
            'pattern' => 'NETFLIX SPECIAL',
            'match_strategy' => MatchStrategy::Exact,
            'priority' => 1,
            'enabled' => true,
        ]);

        MerchantAlias::factory()->create([
            'merchant_id' => $merchant->id,
            'pattern' => 'NETFLIX SPECIAL',
            'match_strategy' => MatchStrategy::Prefix,
            'priority' => 1,
            'enabled' => true,
        ]);

        // Recreate competing exact with worse priority than a prefix would have.
        MerchantAlias::query()
            ->where('normalized_pattern', CatalogNormalizer::descriptor('NETFLIX SPECIAL'))
            ->where('match_strategy', MatchStrategy::Exact->value)
            ->update(['priority' => 50]);

        MerchantAlias::query()
            ->where('normalized_pattern', CatalogNormalizer::descriptor('NETFLIX SPECIAL'))
            ->where('match_strategy', MatchStrategy::Prefix->value)
            ->update(['priority' => 1]);

        $result = $this->resolver->resolve('NETFLIX SPECIAL');

        $this->assertNotNull($result);
        $this->assertSame(MatchStrategy::Exact, $result->strategy);
    }

    private function seedCatalogAliases(): void
    {
        $netflix = Merchant::query()->create([
            'name' => 'Netflix',
            'logo_key' => 'netflix',
        ]);
        $spotify = Merchant::query()->create([
            'name' => 'Spotify',
            'logo_key' => 'spotify',
        ]);
        $shell = Merchant::query()->create([
            'name' => 'Shell',
            'logo_key' => 'shell',
        ]);

        foreach ([
            [$netflix, 'NETFLIX', MatchStrategy::Exact, 10],
            [$netflix, 'NETFLIX', MatchStrategy::Prefix, 20],
            [$netflix, 'NETFLIX.COM', MatchStrategy::Prefix, 15],
            [$netflix, 'NETFLIX-NY', MatchStrategy::Exact, 5],
            [$spotify, 'SPOTIFY', MatchStrategy::Exact, 10],
            [$spotify, 'SPOTIFY', MatchStrategy::Prefix, 20],
            [$spotify, 'SPOTIFY', MatchStrategy::WholeToken, 25],
            [$spotify, 'SPOTIFY USA', MatchStrategy::Exact, 5],
            [$shell, 'SHELL', MatchStrategy::Exact, 10],
            [$shell, 'SHELL GAS', MatchStrategy::Exact, 5],
            [$shell, 'SHELL', MatchStrategy::WholeToken, 30],
            [$shell, 'SHELL', MatchStrategy::SafeContains, 40],
        ] as [$merchant, $pattern, $strategy, $priority]) {
            MerchantAlias::query()->create([
                'merchant_id' => $merchant->id,
                'pattern' => $pattern,
                'match_strategy' => $strategy,
                'priority' => $priority,
                'enabled' => true,
            ]);
        }
    }
}
