<?php

namespace App\Http\Resources;

use App\Services\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_name' => $this->institution_name,
            'name' => $this->name,
            'mask' => $this->mask,
            'type' => $this->type,
            'balance_cents' => $this->balance_cents,
            'balance' => Money::centsToDollarString((int) $this->balance_cents),
            'sync_status' => $this->sync_status?->value ?? $this->sync_status,
            'logo_key' => $this->logo_key,
            'sort_order' => $this->sort_order,
        ];
    }
}
