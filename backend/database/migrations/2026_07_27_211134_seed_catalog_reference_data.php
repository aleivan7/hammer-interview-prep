<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $categories = [
            ['need', 'Housing', 10],
            ['need', 'Groceries', 20],
            ['need', 'Transportation', 30],
            ['need', 'Utilities', 40],
            ['need', 'Health', 50],
            ['need', 'Services', 60],
            ['want', 'Dining', 10],
            ['want', 'Shopping', 20],
            ['want', 'Entertainment', 30],
            ['want', 'Transportation', 40],
            ['want', 'Travel', 50],
            ['want', 'Charity', 60],
            ['want', 'Health', 70],
            ['savings', 'Income', 10],
            ['savings', 'Transfer', 20],
            ['savings', 'Investing', 30],
            ['savings', 'Retirement', 40],
            ['savings', 'Debt', 50],
        ];

        foreach ($categories as [$bucket, $name, $sortOrder]) {
            DB::table('categories')->updateOrInsert(
                [
                    'user_id' => null,
                    'bucket' => $bucket,
                    'normalized_name' => mb_strtolower($name),
                ],
                [
                    'name' => $name,
                    'sort_order' => $sortOrder,
                    'archived_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $catalog = [
            'Spotify' => ['spotify', [
                ['SPOTIFY', 'exact', 10],
                ['SPOTIFY', 'prefix', 20],
                ['SPOTIFY', 'whole_token', 25],
                ['SPOTIFY USA', 'exact', 5],
            ]],
            'Shell' => ['shell', [
                ['SHELL', 'exact', 10],
                ['SHELL GAS', 'exact', 5],
                ['SHELL', 'whole_token', 30],
            ]],
            'Netflix' => ['netflix', [
                ['NETFLIX', 'exact', 10],
                ['NETFLIX', 'prefix', 20],
                ['NETFLIX.COM', 'prefix', 15],
                ['NETFLIX-NY', 'exact', 5],
            ]],
            'DoorDash' => ['doordash', [
                ['DOORDASH', 'exact', 10],
                ['DOORDASH', 'prefix', 20],
            ]],
            'Amazon' => ['amazon', [
                ['AMAZON', 'exact', 10],
                ['AMAZON', 'prefix', 20],
            ]],
            'Starbucks' => ['starbucks', [
                ['STARBUCKS', 'exact', 10],
                ['STARBUCKS', 'prefix', 20],
            ]],
            'Uber' => ['uber', [
                ['UBER', 'exact', 10],
                ['UBER', 'prefix', 20],
                ['UBER EATS', 'exact', 5],
            ]],
            'Delta' => ['delta', [
                ['DELTA', 'exact', 10],
                ['DELTA AIR', 'prefix', 15],
            ]],
            'Vanguard' => ['vanguard', [
                ['VANGUARD', 'exact', 10],
                ['VANGUARD', 'prefix', 20],
            ]],
            'Landlord LLC' => [null, [
                ['LANDLORD', 'exact', 10],
                ['LANDLORD', 'prefix', 20],
            ]],
            'Mortgage Servicing Co' => [null, [
                ['MORTGAGE SERVICING', 'prefix', 10],
                ['MORTGAGE', 'whole_token', 40],
            ]],
            'United Way' => [null, [
                ['UNITED WAY', 'exact', 10],
                ['UNITED WAY', 'prefix', 20],
            ]],
            'HEB' => ['heb', [
                ['HEB', 'exact', 10],
                ['HEB', 'whole_token', 20],
            ]],
            'Chipotle' => ['chipotle', [
                ['CHIPOTLE', 'exact', 10],
                ['CHIPOTLE', 'prefix', 20],
            ]],
            'Target' => ['target', [
                ['TARGET', 'exact', 10],
                ['TARGET', 'prefix', 20],
            ]],
        ];

        $normalizeDescriptor = static function (string $value): string {
            $normalized = preg_replace('/[^A-Z0-9]+/u', ' ', mb_strtoupper(trim($value))) ?? '';

            return preg_replace('/\s+/u', ' ', trim($normalized)) ?? '';
        };

        foreach ($catalog as $name => [$logoKey, $aliases]) {
            $normalizedName = mb_strtolower($name);
            DB::table('merchants')->updateOrInsert(
                ['normalized_name' => $normalizedName],
                [
                    'name' => $name,
                    'logo_key' => $logoKey,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
            $merchantId = DB::table('merchants')
                ->where('normalized_name', $normalizedName)
                ->value('id');

            foreach ($aliases as [$pattern, $strategy, $priority]) {
                DB::table('merchant_aliases')->updateOrInsert(
                    [
                        'merchant_id' => $merchantId,
                        'normalized_pattern' => $normalizeDescriptor($pattern),
                        'match_strategy' => $strategy,
                    ],
                    [
                        'pattern' => $pattern,
                        'priority' => $priority,
                        'enabled' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('merchant_aliases')->delete();
        DB::table('merchants')->delete();
        DB::table('categories')->whereNull('user_id')->delete();
    }
};
