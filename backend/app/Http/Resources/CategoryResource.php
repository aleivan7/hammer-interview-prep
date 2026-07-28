<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'bucket' => $this->bucket?->value ?? $this->bucket,
            'name' => $this->name,
            'normalized_name' => $this->normalized_name,
            'sort_order' => $this->sort_order,
            'is_system' => $this->user_id === null,
            'archived_at' => $this->archived_at?->toIso8601String(),
        ];
    }
}
