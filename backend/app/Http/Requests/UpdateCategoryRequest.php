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

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        if (! $category instanceof Category) {
            return false;
        }

        return $category->user_id !== null
            && (int) $category->user_id === app(DemoUserContext::class)->id();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'bucket' => ['sometimes', Rule::enum(Bucket::class)],
            'archived' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('name')) {
                return;
            }

            $category = $this->route('category');
            if (! $category instanceof Category) {
                return;
            }

            $userId = app(DemoUserContext::class)->id();
            $bucket = $this->input('bucket', $category->bucket?->value ?? $category->bucket);
            $normalized = CatalogNormalizer::name($this->string('name')->toString());

            $duplicate = Category::query()
                ->active()
                ->where('bucket', $bucket)
                ->where('normalized_name', $normalized)
                ->whereKeyNot($category->id)
                ->where(function ($query) use ($userId): void {
                    $query->whereNull('user_id')
                        ->orWhere('user_id', $userId);
                })
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('name', 'A category with this name already exists in this bucket.');
            }
        });
    }
}
