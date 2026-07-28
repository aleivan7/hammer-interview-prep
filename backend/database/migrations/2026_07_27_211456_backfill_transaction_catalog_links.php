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
        if (! Schema::hasColumn('transactions', 'raw_merchant_descriptor')) {
            return;
        }

        $resolver = app(MerchantResolver::class);
        $categoryIds = DB::table('categories')
            ->whereNull('user_id')
            ->whereNull('archived_at')
            ->get(['id', 'bucket', 'normalized_name'])
            ->groupBy(fn ($row) => $row->bucket.'|'.$row->normalized_name)
            ->map(fn ($rows) => $rows->first()->id);

        DB::table('transactions')
            ->orderBy('id')
            ->chunkById(100, function ($transactions) use ($resolver, $categoryIds): void {
                foreach ($transactions as $transaction) {
                    $raw = $transaction->raw_merchant_descriptor ?: $transaction->merchant;
                    $merchantId = null;

                    if (is_string($raw) && trim($raw) !== '') {
                        $resolution = $resolver->resolve($raw);
                        $merchantId = $resolution?->merchant->id;
                    }

                    $categoryId = null;
                    if ($transaction->bucket !== null && $transaction->subcategory !== null) {
                        $key = $transaction->bucket.'|'.CatalogNormalizer::name((string) $transaction->subcategory);
                        $categoryId = $categoryIds[$key] ?? null;
                    }

                    DB::table('transactions')->where('id', $transaction->id)->update([
                        'raw_merchant_descriptor' => $raw,
                        'merchant_id' => $merchantId,
                        'category_id' => $categoryId,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('transactions', 'raw_merchant_descriptor')) {
            return;
        }

        DB::table('transactions')->update([
            'merchant_id' => null,
            'category_id' => null,
        ]);
    }
};
