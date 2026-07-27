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
            'merchant_id' => $this->merchant_id,
            'merchant_contains' => $this->merchant_contains,
            'canonical_merchant' => $this->whenLoaded('merchant', fn () => $this->merchant === null ? null : [
                'id' => $this->merchant->id,
                'name' => $this->merchant->name,
                'normalized_name' => $this->merchant->normalized_name,
                'logo_key' => $this->merchant->logo_key,
            ]),
            'account_id' => $this->account_id,
            'amount_cents_min' => $this->amount_cents_min,
            'amount_cents_max' => $this->amount_cents_max,
            'category_id' => $this->category_id,
            'target_category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'bucket' => $this->category->bucket?->value ?? $this->category->bucket,
                'is_system' => $this->category->user_id === null,
                'archived_at' => $this->category->archived_at?->toIso8601String(),
            ]),
            'target_bucket' => $this->target_bucket?->value ?? $this->target_bucket,
            'target_subcategory' => $this->target_subcategory,
            'priority' => $this->priority,
            'enabled' => $this->enabled,
            'auto_review' => $this->auto_review,
        ];
    }
}
