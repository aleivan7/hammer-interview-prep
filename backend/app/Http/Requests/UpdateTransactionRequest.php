<?php

namespace App\Http\Requests;

use App\Enums\Bucket;
use App\Enums\TransactionKind;
use App\Models\Transaction;
use App\Support\DemoUserContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        if (! $transaction instanceof Transaction) {
            return false;
        }

        return (int) $transaction->user_id === app(DemoUserContext::class)->id();
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
        $userId = app(DemoUserContext::class)->id();

        return [
            'merchant' => ['sometimes', 'string', 'max:255'],
            'amount_cents' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'kind' => ['sometimes', Rule::enum(TransactionKind::class)],
            'bucket' => [
                Rule::requiredIf(fn () => $this->requiresReviewBucket() && ! $this->filled('category') && ! $this->filled('category_id')),
                'nullable',
                Rule::enum(Bucket::class),
            ],
            'subcategory' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) use ($userId): void {
                    $query->whereNull('archived_at')
                        ->where(function ($visible) use ($userId): void {
                            $visible->whereNull('user_id')
                                ->orWhere('user_id', $userId);
                        });
                }),
            ],
            'transaction_date' => ['sometimes', 'date'],
            'account_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'reviewed' => ['sometimes', 'boolean'],
            'category' => [
                Rule::requiredIf(fn () => $this->requiresReviewBucket() && ! $this->filled('bucket') && ! $this->filled('category_id')),
                Rule::in([...Bucket::values(), 'debt_savings']),
            ],
        ];
    }

    private function requiresReviewBucket(): bool
    {
        $transaction = $this->route('transaction');
        $willBeReviewed = $this->exists('reviewed')
            ? $this->boolean('reviewed')
            : $transaction instanceof Transaction && $transaction->isReviewed();

        if (! $willBeReviewed) {
            return false;
        }

        if ($this->filled('bucket') || $this->filled('category') || $this->filled('category_id')) {
            return false;
        }

        if ($this->exists('bucket') || $this->exists('category') || $this->exists('category_id')) {
            return true;
        }

        return ! $transaction instanceof Transaction || $transaction->bucket === null;
    }
}
