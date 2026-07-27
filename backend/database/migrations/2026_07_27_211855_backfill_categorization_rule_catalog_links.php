<?php

use App\Services\MerchantResolver;
use App\Support\CatalogNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categorization_rules', 'merchant_id')) {
            return;
        }

        $resolver = app(MerchantResolver::class);

        $merchantsByNormalized = DB::table('merchants')
            ->get(['id', 'normalized_name'])
            ->keyBy('normalized_name');

        $categoryIds = DB::table('categories')
            ->whereNull('user_id')
            ->whereNull('archived_at')
            ->get(['id', 'bucket', 'normalized_name'])
            ->groupBy(fn ($row) => $row->bucket.'|'.$row->normalized_name)
            ->map(fn ($rows) => $rows->first()->id);

        DB::table('categorization_rules')
            ->orderBy('id')
            ->chunkById(100, function ($rules) use ($resolver, $merchantsByNormalized, $categoryIds): void {
                foreach ($rules as $rule) {
                    $merchantId = $rule->merchant_id;
                    $contains = is_string($rule->merchant_contains) ? trim($rule->merchant_contains) : '';

                    if ($merchantId === null && $contains !== '') {
                        $normalizedContains = CatalogNormalizer::name($contains);
                        $exactMerchant = $merchantsByNormalized[$normalizedContains] ?? null;

                        if ($exactMerchant !== null) {
                            $merchantId = $exactMerchant->id;
                        } else {
                            $resolution = $resolver->resolve($contains);
                            $merchantId = $resolution?->merchant->id;
                        }
                    }

                    $categoryId = $rule->category_id;
                    if ($categoryId === null && $rule->target_bucket !== null && $rule->target_subcategory !== null) {
                        $key = $rule->target_bucket.'|'.CatalogNormalizer::name((string) $rule->target_subcategory);
                        $categoryId = $categoryIds[$key] ?? null;
                    }

                    if ($merchantId === null && $categoryId === null) {
                        continue;
                    }

                    DB::table('categorization_rules')->where('id', $rule->id)->update([
                        'merchant_id' => $merchantId,
                        'category_id' => $categoryId,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('categorization_rules', 'merchant_id')) {
            return;
        }

        DB::table('categorization_rules')->update([
            'merchant_id' => null,
            'category_id' => null,
        ]);
    }
};
