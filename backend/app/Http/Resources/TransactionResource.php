<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Explicit API shape for the Vue frontend.
        return [
            'id' => $this->id,
            'merchant' => $this->merchant,
            // Keep amount as a two-decimal string for stable JSON.
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'category' => $this->category,
            'transaction_date' => $this->transaction_date instanceof \DateTimeInterface
                ? $this->transaction_date->format('Y-m-d')
                : $this->transaction_date,
            // Frontend receives a boolean instead of the raw reviewed_at timestamp.
            'reviewed' => $this->reviewed_at !== null,
        ];
    }
}
