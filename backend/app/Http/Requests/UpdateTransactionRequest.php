<?php

namespace App\Http\Requests;

use App\Enums\Bucket;
use App\Enums\TransactionKind;
use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('category') && ! $this->has('bucket')) {
            $category = $this->input('category');
            $this->merge([
                'bucket' => $category === 'debt_savings' ? 'savings' : $category,
            ]);
        }

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
        return [
            'merchant' => ['sometimes', 'string', 'max:255'],
            'amount_cents' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'kind' => ['sometimes', Rule::enum(TransactionKind::class)],
            'bucket' => [
                Rule::requiredIf(fn () => $this->requiresReviewBucket() && ! $this->filled('category')),
                'nullable',
                Rule::enum(Bucket::class),
            ],
            'subcategory' => ['sometimes', 'nullable', 'string', 'max:100'],
            'transaction_date' => ['sometimes', 'date'],
            'account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'reviewed' => ['sometimes', 'boolean'],
            'category' => [
                Rule::requiredIf(fn () => $this->requiresReviewBucket() && ! $this->filled('bucket')),
                Rule::in([...Bucket::values(), 'debt_savings']),
            ],
        ];
    }

    private function requiresReviewBucket(): bool
    {
        if (! $this->boolean('reviewed')) {
            return false;
        }

        $transaction = $this->route('transaction');

        return ! $transaction instanceof Transaction || $transaction->bucket === null;
    }
}
