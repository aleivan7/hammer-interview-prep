<?php

namespace App\Models;

use App\Enums\Bucket;
use Database\Factories\PlannedCashFlowFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlannedCashFlow extends Model
{
    /** @use HasFactory<PlannedCashFlowFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'amount_cents',
        'kind',
        'due_on',
        'is_essential',
        'bucket',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'amount_cents' => 'integer',
            'due_on' => 'date',
            'is_essential' => 'boolean',
            'bucket' => Bucket::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
