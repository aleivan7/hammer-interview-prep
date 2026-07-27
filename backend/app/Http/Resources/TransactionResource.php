<?php

namespace App\Http\Resources;

use App\Services\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account' => $this->whenLoaded('account', fn () => [
                'id' => $this->account?->id,
                'name' => $this->account?->name,
                'institution_name' => $this->account?->institution_name,
            ]),
            'merchant' => $this->merchant,
            'raw_merchant_descriptor' => $this->raw_merchant_descriptor ?? $this->merchant,
            'merchant_id' => $this->merchant_id,
            'canonical_merchant' => $this->whenLoaded('canonicalMerchant', fn () => $this->canonicalMerchant === null ? null : [
                'id' => $this->canonicalMerchant->id,
                'name' => $this->canonicalMerchant->name,
                'normalized_name' => $this->canonicalMerchant->normalized_name,
                'logo_key' => $this->canonicalMerchant->logo_key,
            ]),
            'amount_cents' => $this->amount_cents,
            'amount' => Money::centsToDollarString((int) $this->amount_cents),
            'kind' => $this->kind?->value ?? $this->kind,
            'bucket' => $this->bucket?->value,
            'subcategory' => $this->subcategory,
            'category_id' => $this->category_id,
            'detailed_category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'bucket' => $this->category->bucket?->value ?? $this->category->bucket,
                'is_system' => $this->category->user_id === null,
                'archived_at' => $this->category->archived_at?->toIso8601String(),
            ]),
            'transaction_date' => $this->transaction_date instanceof \DateTimeInterface
                ? $this->transaction_date->format('Y-m-d')
                : $this->transaction_date,
            'reviewed' => $this->reviewed_at !== null,
            'review_source' => $this->review_source?->value,
            'confidence' => $this->confidence,
            'review_explanation' => $this->review_explanation,
            'notes' => $this->notes,
            // Legacy alias used by older clients/tests during transition.
            'category' => $this->bucket?->value,
        ];
    }
}
