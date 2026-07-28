<?php

namespace App\Models;

use App\Support\CatalogNormalizer;
use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'normalized_name',
        'logo_key',
    ];

    protected static function booted(): void
    {
        static::saving(function (Merchant $merchant): void {
            $merchant->normalized_name = CatalogNormalizer::name($merchant->name);
        });
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(MerchantAlias::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($term)).'%';

        return $query->where(function (Builder $builder) use ($like): void {
            $builder->whereRaw('lower(name) like ?', [$like])
                ->orWhereRaw('normalized_name like ?', [$like])
                ->orWhereHas('aliases', function (Builder $aliasQuery) use ($like): void {
                    $aliasQuery->whereRaw('lower(pattern) like ?', [$like])
                        ->orWhereRaw('normalized_pattern like ?', [$like]);
                });
        });
    }
}
