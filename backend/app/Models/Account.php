<?php

namespace App\Models;

use App\Enums\AccountSyncStatus;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'institution_name',
        'name',
        'mask',
        'type',
        'balance_cents',
        'sync_status',
        'logo_key',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'balance_cents' => 'integer',
            'sync_status' => AccountSyncStatus::class,
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
