<?php

namespace App\Http\Requests;

use App\Enums\Bucket;
use App\Models\CategorizationRule;
use App\Support\DemoUserContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategorizationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('categorization_rule');

        if (! $rule instanceof CategorizationRule) {
            return false;
        }

        return (int) $rule->user_id === app(DemoUserContext::class)->id();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = app(DemoUserContext::class)->id();

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'merchant_contains' => ['sometimes', 'string', 'max:120'],
            'account_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'amount_cents_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'amount_cents_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'target_bucket' => ['sometimes', Rule::enum(Bucket::class)],
            'target_subcategory' => ['sometimes', 'nullable', 'string', 'max:100'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'enabled' => ['sometimes', 'boolean'],
            'auto_review' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['amount_cents_min', 'amount_cents_max'])) {
                    return;
                }

                $rule = $this->route('categorization_rule');

                if (! $rule instanceof CategorizationRule) {
                    return;
                }

                $minimum = $this->exists('amount_cents_min')
                    ? $this->input('amount_cents_min')
                    : $rule->amount_cents_min;
                $maximum = $this->exists('amount_cents_max')
                    ? $this->input('amount_cents_max')
                    : $rule->amount_cents_max;

                if ($minimum === null || $maximum === null || (int) $maximum >= (int) $minimum) {
                    return;
                }

                $field = $this->exists('amount_cents_max')
                    ? 'amount_cents_max'
                    : 'amount_cents_min';

                $validator->errors()->add(
                    $field,
                    'The maximum amount must be greater than or equal to the minimum amount.',
                );
            },
        ];
    }
}
