<?php

namespace App\Models;

use App\Enums\Bucket;
use Database\Factories\CategorizationRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategorizationRule extends Model
{
    /** @use HasFactory<CategorizationRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'merchant_contains',
        'account_id',
        'amount_cents_min',
        'amount_cents_max',
        'target_bucket',
        'target_subcategory',
        'priority',
        'enabled',
        'auto_review',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'amount_cents_min' => 'integer',
            'amount_cents_max' => 'integer',
            'target_bucket' => Bucket::class,
            'priority' => 'integer',
            'enabled' => 'boolean',
            'auto_review' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
