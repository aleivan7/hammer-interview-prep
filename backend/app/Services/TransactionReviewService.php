<?php

namespace App\Services;

use App\Contracts\TransactionCategorizer;
use App\Enums\Bucket;
use App\Enums\ReviewSource;
use App\Models\ReviewAudit;
use App\Models\Transaction;
use App\Support\CategorizationResult;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TransactionReviewService
{
    public function __construct(
        private readonly TransactionCategorizer $categorizer,
    ) {}

    public function review(
        Transaction $transaction,
        Bucket $bucket,
        ?string $subcategory,
        ReviewSource $source,
        ?int $confidence = null,
        ?string $explanation = null,
        ?string $idempotencyKey = null,
        ?int $categoryId = null,
    ): Transaction {
        return DB::transaction(function () use ($transaction, $bucket, $subcategory, $source, $confidence, $explanation, $idempotencyKey, $categoryId) {
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($idempotencyKey !== null) {
                $existing = Transaction::query()
                    ->where('user_id', $transaction->user_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            if ($transaction->isReviewed() && $idempotencyKey === null) {
                throw new InvalidArgumentException('Transaction is already reviewed.');
            }

            $previous = [
                'bucket' => $transaction->bucket?->value,
                'subcategory' => $transaction->subcategory,
                'category_id' => $transaction->category_id,
                'raw_merchant_descriptor' => $transaction->raw_merchant_descriptor,
                'merchant_id' => $transaction->merchant_id,
                'reviewed_at' => $transaction->reviewed_at?->toISOString(),
                'review_source' => $transaction->review_source?->value,
                'confidence' => $transaction->confidence,
            ];

            $transaction->update([
                'bucket' => $bucket,
                'subcategory' => $subcategory,
                'category_id' => $categoryId,
                'reviewed_at' => now(),
                'review_source' => $source,
                'confidence' => $confidence,
                'review_explanation' => $explanation,
                'idempotency_key' => $idempotencyKey,
            ]);

            ReviewAudit::query()->create([
                'transaction_id' => $transaction->id,
                'action' => 'review',
                'source' => $source,
                'bucket' => $bucket,
                'subcategory' => $subcategory,
                'confidence' => $confidence,
                'explanation' => $explanation,
                'previous_state' => $previous,
            ]);

            return $transaction->refresh();
        });
    }

    public function undo(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction) {
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if (! $transaction->isReviewed()) {
                throw new InvalidArgumentException('Transaction is not reviewed.');
            }

            $audit = ReviewAudit::query()
                ->where('transaction_id', $transaction->id)
                ->where('action', 'review')
                ->latest('id')
                ->first();

            $previous = $audit?->previous_state ?? [];
            $rawDescriptor = $transaction->raw_merchant_descriptor;
            $merchantId = $transaction->merchant_id;

            $transaction->update([
                'bucket' => $previous['bucket'] ?? null,
                'subcategory' => $previous['subcategory'] ?? null,
                'category_id' => $previous['category_id'] ?? null,
                'raw_merchant_descriptor' => $previous['raw_merchant_descriptor'] ?? $rawDescriptor,
                'merchant_id' => array_key_exists('merchant_id', $previous) ? $previous['merchant_id'] : $merchantId,
                'reviewed_at' => null,
                'review_source' => null,
                'confidence' => null,
                'review_explanation' => null,
                'idempotency_key' => null,
            ]);

            ReviewAudit::query()->create([
                'transaction_id' => $transaction->id,
                'action' => 'undo',
                'source' => ReviewSource::Undo,
                'bucket' => null,
                'subcategory' => null,
                'confidence' => null,
                'explanation' => 'Review undone.',
                'previous_state' => [
                    'bucket' => $audit?->bucket?->value,
                    'subcategory' => $audit?->subcategory,
                    'category_id' => $previous['category_id'] ?? null,
                ],
            ]);

            return $transaction->refresh();
        });
    }

    public function suggest(Transaction $transaction): CategorizationResult
    {
        return $this->categorizer->categorize($transaction);
    }
}
