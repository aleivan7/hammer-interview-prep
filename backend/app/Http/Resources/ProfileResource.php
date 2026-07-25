<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $plan = $this->financialPlan;
        $accounts = $this->accounts;
        $totalBalanceCents = (int) $accounts->sum('balance_cents');
        $monthlyIncomeCents = (int) ($plan?->monthly_income_cents ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'persona_type' => $this->persona_type?->value,
            'persona_label' => $this->persona_label ?? $this->persona_type?->label(),
            'description' => $this->description,
            'member_since' => $this->member_since?->format('Y-m-d'),
            'avatar_initials' => $this->avatar_initials,
            'monthly_income_cents' => $monthlyIncomeCents,
            'monthly_income' => Money::centsToDollarString($monthlyIncomeCents),
            'total_balance_cents' => $totalBalanceCents,
            'total_balance' => Money::centsToDollarString($totalBalanceCents),
            'account_count' => $accounts->count(),
            'plan' => $plan ? [
                'needs_percent' => $plan->needs_percent,
                'wants_percent' => $plan->wants_percent,
                'savings_percent' => $plan->savings_percent,
                'safety_buffer_cents' => $plan->safety_buffer_cents,
                'safety_buffer' => Money::centsToDollarString($plan->safety_buffer_cents),
                'monthly_income_cents' => $plan->monthly_income_cents,
                'monthly_income' => Money::centsToDollarString($plan->monthly_income_cents),
            ] : null,
            'accounts' => AccountResource::collection($accounts)->resolve(),
            'financial_status_label' => $this->persona_type?->financialStatusLabel(),
        ];
    }
}
