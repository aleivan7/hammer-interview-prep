<?php

namespace App\Http\Requests;

use App\Enums\Bucket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategorizationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'merchant_contains' => ['required', 'string', 'max:120'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'amount_cents_min' => ['nullable', 'integer', 'min:0'],
            'amount_cents_max' => ['nullable', 'integer', 'min:0', 'gte:amount_cents_min'],
            'target_bucket' => ['required', Rule::enum(Bucket::class)],
            'target_subcategory' => ['nullable', 'string', 'max:100'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'enabled' => ['sometimes', 'boolean'],
            'auto_review' => ['sometimes', 'boolean'],
        ];
    }
}
