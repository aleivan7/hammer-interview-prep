<?php

namespace App\Models;

use App\Enums\Bucket;
use Database\Factories\CategorizationRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategorizationRule extends Model
{
    /** @use HasFactory<CategorizationRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'merchant_contains',
        'merchant_id',
        'account_id',
        'amount_cents_min',
        'amount_cents_max',
        'target_bucket',
        'target_subcategory',
        'category_id',
        'priority',
        'enabled',
        'auto_review',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'merchant_id' => 'integer',
            'account_id' => 'integer',
            'amount_cents_min' => 'integer',
            'amount_cents_max' => 'integer',
            'category_id' => 'integer',
            'target_bucket' => Bucket::class,
            'priority' => 'integer',
            'enabled' => 'boolean',
            'auto_review' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
