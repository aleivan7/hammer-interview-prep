<?php

namespace Database\Seeders;

use App\Enums\Bucket;
use App\Enums\MatchStrategy;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantAlias;
use App\Support\CatalogNormalizer;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSystemCategories();
        $this->seedMerchantsAndAliases();
    }

    private function seedSystemCategories(): void
    {
        $categories = [
            [Bucket::Need, 'Housing', 10],
            [Bucket::Need, 'Groceries', 20],
            [Bucket::Need, 'Transportation', 30],
            [Bucket::Need, 'Utilities', 40],
            [Bucket::Need, 'Health', 50],
            [Bucket::Need, 'Services', 60],
            [Bucket::Want, 'Dining', 10],
            [Bucket::Want, 'Shopping', 20],
            [Bucket::Want, 'Entertainment', 30],
            [Bucket::Want, 'Transportation', 40],
            [Bucket::Want, 'Travel', 50],
            [Bucket::Want, 'Charity', 60],
            [Bucket::Want, 'Health', 70],
            [Bucket::Savings, 'Income', 10],
            [Bucket::Savings, 'Transfer', 20],
            [Bucket::Savings, 'Investing', 30],
            [Bucket::Savings, 'Retirement', 40],
            [Bucket::Savings, 'Debt', 50],
        ];

        foreach ($categories as [$bucket, $name, $sortOrder]) {
            Category::query()->updateOrCreate(
                [
                    'user_id' => null,
                    'bucket' => $bucket,
                    'normalized_name' => CatalogNormalizer::name($name),
                ],
                [
                    'name' => $name,
                    'sort_order' => $sortOrder,
                    'archived_at' => null,
                ],
            );
        }
    }

    private function seedMerchantsAndAliases(): void
    {
        $catalog = [
            'Spotify' => [
                'logo_key' => 'spotify',
                'aliases' => [
                    ['SPOTIFY', MatchStrategy::Exact, 10],
                    ['SPOTIFY', MatchStrategy::Prefix, 20],
                    ['SPOTIFY', MatchStrategy::WholeToken, 25],
                    ['SPOTIFY USA', MatchStrategy::Exact, 5],
                ],
            ],
            'Shell' => [
                'logo_key' => 'shell',
                'aliases' => [
                    ['SHELL', MatchStrategy::Exact, 10],
                    ['SHELL GAS', MatchStrategy::Exact, 5],
                    ['SHELL', MatchStrategy::WholeToken, 30],
                ],
            ],
            'Netflix' => [
                'logo_key' => 'netflix',
                'aliases' => [
                    ['NETFLIX', MatchStrategy::Exact, 10],
                    ['NETFLIX', MatchStrategy::Prefix, 20],
                    ['NETFLIX.COM', MatchStrategy::Prefix, 15],
                    ['NETFLIX-NY', MatchStrategy::Exact, 5],
                ],
            ],
            'DoorDash' => [
                'logo_key' => 'doordash',
                'aliases' => [
                    ['DOORDASH', MatchStrategy::Exact, 10],
                    ['DOORDASH', MatchStrategy::Prefix, 20],
                ],
            ],
            'Amazon' => [
                'logo_key' => 'amazon',
                'aliases' => [
                    ['AMAZON', MatchStrategy::Exact, 10],
                    ['AMAZON', MatchStrategy::Prefix, 20],
                ],
            ],
            'Starbucks' => [
                'logo_key' => 'starbucks',
                'aliases' => [
                    ['STARBUCKS', MatchStrategy::Exact, 10],
                    ['STARBUCKS', MatchStrategy::Prefix, 20],
                ],
            ],
            'Uber' => [
                'logo_key' => 'uber',
                'aliases' => [
                    ['UBER', MatchStrategy::Exact, 10],
                    ['UBER', MatchStrategy::Prefix, 20],
                    ['UBER EATS', MatchStrategy::Exact, 5],
                ],
            ],
            'Delta' => [
                'logo_key' => 'delta',
                'aliases' => [
                    ['DELTA', MatchStrategy::Exact, 10],
                    ['DELTA AIR', MatchStrategy::Prefix, 15],
                ],
            ],
            'Vanguard' => [
                'logo_key' => 'vanguard',
                'aliases' => [
                    ['VANGUARD', MatchStrategy::Exact, 10],
                    ['VANGUARD', MatchStrategy::Prefix, 20],
                ],
            ],
            'Landlord LLC' => [
                'logo_key' => null,
                'aliases' => [
                    ['LANDLORD', MatchStrategy::Exact, 10],
                    ['LANDLORD', MatchStrategy::Prefix, 20],
                ],
            ],
            'Mortgage Servicing Co' => [
                'logo_key' => null,
                'aliases' => [
                    ['MORTGAGE SERVICING', MatchStrategy::Prefix, 10],
                    ['MORTGAGE', MatchStrategy::WholeToken, 40],
                ],
            ],
            'United Way' => [
                'logo_key' => null,
                'aliases' => [
                    ['UNITED WAY', MatchStrategy::Exact, 10],
                    ['UNITED WAY', MatchStrategy::Prefix, 20],
                ],
            ],
            'HEB' => [
                'logo_key' => 'heb',
                'aliases' => [
                    ['HEB', MatchStrategy::Exact, 10],
                    ['HEB', MatchStrategy::WholeToken, 20],
                ],
            ],
            'Chipotle' => [
                'logo_key' => 'chipotle',
                'aliases' => [
                    ['CHIPOTLE', MatchStrategy::Exact, 10],
                    ['CHIPOTLE', MatchStrategy::Prefix, 20],
                ],
            ],
            'Target' => [
                'logo_key' => 'target',
                'aliases' => [
                    ['TARGET', MatchStrategy::Exact, 10],
                    ['TARGET', MatchStrategy::Prefix, 20],
                ],
            ],
        ];

        foreach ($catalog as $name => $definition) {
            $merchant = Merchant::query()->updateOrCreate(
                ['normalized_name' => CatalogNormalizer::name($name)],
                [
                    'name' => $name,
                    'logo_key' => $definition['logo_key'],
                ],
            );

            foreach ($definition['aliases'] as [$pattern, $strategy, $priority]) {
                MerchantAlias::query()->updateOrCreate(
                    [
                        'merchant_id' => $merchant->id,
                        'normalized_pattern' => CatalogNormalizer::descriptor($pattern),
                        'match_strategy' => $strategy,
                    ],
                    [
                        'pattern' => $pattern,
                        'priority' => $priority,
                        'enabled' => true,
                    ],
                );
            }
        }
    }
}
