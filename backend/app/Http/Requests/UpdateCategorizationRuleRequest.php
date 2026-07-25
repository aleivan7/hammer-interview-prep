<?php

namespace App\Http\Requests;

use App\Enums\Bucket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategorizationRuleRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120'],
            'merchant_contains' => ['sometimes', 'string', 'max:120'],
            'account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'amount_cents_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'amount_cents_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'target_bucket' => ['sometimes', Rule::enum(Bucket::class)],
            'target_subcategory' => ['sometimes', 'nullable', 'string', 'max:100'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'enabled' => ['sometimes', 'boolean'],
            'auto_review' => ['sometimes', 'boolean'],
        ];
    }
}
