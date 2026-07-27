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

class StoreCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'bucket' => ['required', Rule::enum(Bucket::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $userId = app(DemoUserContext::class)->id();
            $bucket = $this->string('bucket')->toString();
            $normalized = CatalogNormalizer::name($this->string('name')->toString());

            $duplicate = Category::query()
                ->active()
                ->where('bucket', $bucket)
                ->where('normalized_name', $normalized)
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
