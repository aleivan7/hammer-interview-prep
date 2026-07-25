<?php

namespace App\Models;

use App\Enums\Bucket;
use App\Enums\ReviewSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewAudit extends Model
{
    protected $fillable = [
        'transaction_id',
        'action',
        'source',
        'bucket',
        'subcategory',
        'confidence',
        'explanation',
        'previous_state',
    ];

    protected function casts(): array
    {
        return [
            'source' => ReviewSource::class,
            'bucket' => Bucket::class,
            'confidence' => 'integer',
            'previous_state' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
