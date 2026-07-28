<?php

namespace App\Http\Requests;

use App\Enums\Bucket;
use App\Models\Category;
use App\Support\CatalogNormalizer;
use App\Support\DemoUserContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $userId = app(DemoUserContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:120'],
            'merchant_id' => ['required', 'integer', Rule::exists('merchants', 'id')],
            'merchant_contains' => ['sometimes', 'nullable', 'string', 'max:120'],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'amount_cents_min' => ['nullable', 'integer', 'min:0'],
            'amount_cents_max' => ['nullable', 'integer', 'min:0', 'gte:amount_cents_min'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) use ($userId): void {
                    $query->whereNull('archived_at')
                        ->where(function ($visible) use ($userId): void {
                            $visible->whereNull('user_id')
                                ->orWhere('user_id', $userId);
                        });
                }),
            ],
            'target_bucket' => ['sometimes', 'nullable', Rule::enum(Bucket::class)],
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
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $category = Category::query()->find($this->integer('category_id'));

                if ($category === null) {
                    return;
                }

                if ($this->filled('target_bucket') && $this->input('target_bucket') !== $category->bucket->value) {
                    $validator->errors()->add(
                        'target_bucket',
                        'The target bucket must match the selected category bucket.',
                    );
                }

                if (
                    $this->filled('target_subcategory')
                    && CatalogNormalizer::name($this->string('target_subcategory')->toString()) !== $category->normalized_name
                ) {
                    $validator->errors()->add(
                        'target_subcategory',
                        'The target subcategory must match the selected category.',
                    );
                }
            },
        ];
    }
}
