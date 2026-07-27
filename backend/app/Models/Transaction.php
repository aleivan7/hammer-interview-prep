<?php

namespace App\Models;

use App\Enums\Bucket;
use App\Enums\ReviewSource;
use App\Enums\TransactionKind;
use App\Services\MerchantResolver;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'merchant',
        'raw_merchant_descriptor',
        'merchant_id',
        'amount_cents',
        'kind',
        'bucket',
        'subcategory',
        'category_id',
        'transaction_date',
        'reviewed_at',
        'review_source',
        'confidence',
        'review_explanation',
        'notes',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'account_id' => 'integer',
            'merchant_id' => 'integer',
            'category_id' => 'integer',
            'amount_cents' => 'integer',
            'kind' => TransactionKind::class,
            'bucket' => Bucket::class,
            'transaction_date' => 'date',
            'reviewed_at' => 'datetime',
            'review_source' => ReviewSource::class,
            'confidence' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Transaction $transaction): void {
            if ($transaction->isDirty('merchant') && ! $transaction->isDirty('raw_merchant_descriptor')) {
                $transaction->raw_merchant_descriptor = $transaction->merchant;
            }

            if ($transaction->raw_merchant_descriptor === null && is_string($transaction->merchant)) {
                $transaction->raw_merchant_descriptor = $transaction->merchant;
            }

            if ($transaction->isDirty('raw_merchant_descriptor') || $transaction->isDirty('merchant')) {
                $resolution = app(MerchantResolver::class)->resolve((string) $transaction->raw_merchant_descriptor);
                $transaction->merchant_id = $resolution?->merchant->id;
            }

            if ($transaction->isDirty('category_id') && $transaction->category_id !== null) {
                $category = Category::query()->find($transaction->category_id);
                if ($category !== null) {
                    $transaction->bucket = $category->bucket;
                    $transaction->subcategory = $category->name;
                }
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function canonicalMerchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviewAudits(): HasMany
    {
        return $this->hasMany(ReviewAudit::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeUnreviewed(Builder $query): Builder
    {
        return $query->whereNull('reviewed_at');
    }

    public function scopeReviewed(Builder $query): Builder
    {
        return $query->whereNotNull('reviewed_at');
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }
}
