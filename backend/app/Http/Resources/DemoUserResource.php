<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class DemoUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $monthlyIncomeCents = (int) ($this->financialPlan?->monthly_income_cents ?? 0);
        $accountCount = (int) ($this->accounts_count ?? $this->accounts->count());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'persona_type' => $this->persona_type?->value,
            'persona_label' => $this->persona_label ?? $this->persona_type?->label(),
            'description' => $this->description,
            'avatar_initials' => $this->avatar_initials,
            'monthly_income_cents' => $monthlyIncomeCents,
            'monthly_income' => Money::centsToDollarString($monthlyIncomeCents),
            'account_count' => $accountCount,
            'financial_status_label' => $this->persona_type?->financialStatusLabel(),
        ];
    }
}
