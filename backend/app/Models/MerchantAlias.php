<?php

namespace App\Models;

use App\Enums\MatchStrategy;
use App\Support\CatalogNormalizer;
use Database\Factories\MerchantAliasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantAlias extends Model
{
    /** @use HasFactory<MerchantAliasFactory> */
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'pattern',
        'normalized_pattern',
        'match_strategy',
        'priority',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'merchant_id' => 'integer',
            'match_strategy' => MatchStrategy::class,
            'priority' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MerchantAlias $alias): void {
            $alias->normalized_pattern = CatalogNormalizer::descriptor($alias->pattern);
        });
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }
}
