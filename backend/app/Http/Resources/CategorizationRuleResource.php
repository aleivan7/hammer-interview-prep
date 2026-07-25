<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategorizationRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'merchant_contains' => $this->merchant_contains,
            'account_id' => $this->account_id,
            'amount_cents_min' => $this->amount_cents_min,
            'amount_cents_max' => $this->amount_cents_max,
            'target_bucket' => $this->target_bucket?->value ?? $this->target_bucket,
            'target_subcategory' => $this->target_subcategory,
            'priority' => $this->priority,
            'enabled' => $this->enabled,
            'auto_review' => $this->auto_review,
        ];
    }
}
