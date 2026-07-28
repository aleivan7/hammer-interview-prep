<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $exampleDescriptors = $this->whenLoaded('aliases', function () {
            return $this->aliases
                ->sortBy([
                    ['priority', 'asc'],
                    ['id', 'asc'],
                ])
                ->take(5)
                ->values()
                ->map(fn ($alias) => [
                    'pattern' => $alias->pattern,
                    'match_strategy' => $alias->match_strategy?->value ?? $alias->match_strategy,
                    'priority' => $alias->priority,
                    'enabled' => $alias->enabled,
                ])
                ->all();
        }, []);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'normalized_name' => $this->normalized_name,
            'logo_key' => $this->logo_key,
            'example_descriptors' => $exampleDescriptors,
        ];
    }
}
