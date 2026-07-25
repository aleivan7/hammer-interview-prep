<?php

namespace App\Models;

use App\Enums\Bucket;
use App\Enums\ReviewSource;
use App\Enums\TransactionKind;
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
        'account_id',
        'merchant',
        'amount_cents',
        'kind',
        'bucket',
        'subcategory',
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
            'account_id' => 'integer',
            'amount_cents' => 'integer',
            'kind' => TransactionKind::class,
            'bucket' => Bucket::class,
            'transaction_date' => 'date',
            'reviewed_at' => 'datetime',
            'review_source' => ReviewSource::class,
            'confidence' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function reviewAudits(): HasMany
    {
        return $this->hasMany(ReviewAudit::class);
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
