<?php

namespace App\Models;

use App\Enums\Bucket;
use App\Support\CatalogNormalizer;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bucket',
        'name',
        'normalized_name',
        'sort_order',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'bucket' => Bucket::class,
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            $category->normalized_name = CatalogNormalizer::name($category->name);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSystem(): bool
    {
        return $this->user_id === null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $builder) use ($user): void {
            $builder->whereNull('user_id')
                ->orWhere('user_id', $user->id);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
