<?php

namespace App\Services;

use App\Contracts\TransactionCategorizer;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SmartReviewService
{
    public function __construct(
        private readonly TransactionReviewService $reviewService,
        private readonly TransactionCategorizer $categorizer,
    ) {}

    /**
     * @return array{
     *   applied: list<array<string, mixed>>,
     *   skipped: list<array<string, mixed>>,
     *   applied_count: int,
     *   skipped_count: int,
     *   batch_key: string
     * }
     */
    public function run(?string $batchKey = null): array
    {
        $batchKey ??= (string) Str::uuid();

        return DB::transaction(function () use ($batchKey) {
            $applied = [];
            $skipped = [];
            $appliedIds = [];

            // Retry-safe: reconstruct prior applied rows for this batch key.
            $priorApplied = Transaction::query()
                ->where('idempotency_key', 'like', "smart-review:{$batchKey}:%")
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($priorApplied as $transaction) {
                $appliedIds[$transaction->id] = true;
                $applied[] = $this->appliedSummary($transaction);
            }

            $transactions = Transaction::query()
                ->unreviewed()
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($transactions as $transaction) {
                if (isset($appliedIds[$transaction->id])) {
                    continue;
                }

                $result = $this->categorizer->categorize($transaction);

                if (! $result->isConfident()) {
                    $skipped[] = [
                        'id' => $transaction->id,
                        'merchant' => $transaction->merchant,
                        'confidence' => $result->confidence,
                        'explanation' => $result->explanation,
                        'suggested_bucket' => $result->bucket?->value,
                        'suggested_subcategory' => $result->subcategory,
                    ];

                    continue;
                }

                $idempotencyKey = "smart-review:{$batchKey}:{$transaction->id}";

                if ($result->bucket === null) {
                    continue;
                }

                $updated = $this->reviewService->review(
                    transaction: $transaction,
                    bucket: $result->bucket,
                    subcategory: $result->subcategory,
                    source: $result->source,
                    confidence: $result->confidence,
                    explanation: $result->explanation,
                    idempotencyKey: $idempotencyKey,
                );

                $appliedIds[$updated->id] = true;
                $applied[] = $this->appliedSummary($updated);
            }

            return [
                'applied' => $applied,
                'skipped' => $skipped,
                'applied_count' => count($applied),
                'skipped_count' => count($skipped),
                'batch_key' => $batchKey,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function appliedSummary(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'merchant' => $transaction->merchant,
            'bucket' => $transaction->bucket?->value,
            'subcategory' => $transaction->subcategory,
            'confidence' => $transaction->confidence,
            'explanation' => $transaction->review_explanation,
            'source' => $transaction->review_source?->value,
        ];
    }
}
