<?php

namespace App\Http\Requests;

use App\Enums\Bucket;
use App\Enums\TransactionKind;
use App\Support\DemoUserContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('amount') && ! $this->has('amount_cents')) {
            $this->merge([
                'amount_cents' => (int) round(((float) $this->input('amount')) * 100),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = app(DemoUserContext::class)->id();

        return [
            'merchant' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:0', 'max:100000000'],
            'kind' => ['required', Rule::enum(TransactionKind::class)],
            'bucket' => [
                Rule::requiredIf(fn () => $this->boolean('reviewed')),
                'nullable',
                Rule::enum(Bucket::class),
            ],
            'subcategory' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['required', 'date'],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reviewed' => ['sometimes', 'boolean'],
        ];
    }
}
