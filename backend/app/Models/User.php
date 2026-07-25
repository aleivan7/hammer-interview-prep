<?php

namespace App\Models;

use App\Enums\PersonaType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'persona_type',
    'persona_label',
    'description',
    'member_since',
    'avatar_initials',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'persona_type' => PersonaType::class,
            'member_since' => 'date',
        ];
    }

    public function financialPlan(): HasOne
    {
        return $this->hasOne(FinancialPlan::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function plannedCashFlows(): HasMany
    {
        return $this->hasMany(PlannedCashFlow::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function categorizationRules(): HasMany
    {
        return $this->hasMany(CategorizationRule::class);
    }

    public function isDemoPersona(): bool
    {
        return $this->persona_type !== null;
    }
}
