<?php

namespace Tests\Unit;

use App\Enums\Bucket;
use App\Enums\ReviewSource;
use App\Models\ReviewAudit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Transaction review/undo: audit trail restore and guardrails.
 */
class TransactionReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TransactionReviewService::class);
    }

    #[TestDox('Review writes an audit row and undo restores the prior bucket from previous_state')]
    public function test_undo_restores_previous_bucket_from_review_audit(): void
    {
        $user = User::factory()->average()->create();
        $transaction = Transaction::factory()->for($user)->unreviewed()->create([
            'bucket' => Bucket::Need,
            'subcategory' => 'groceries',
            'merchant' => 'HEB',
        ]);

        $this->service->review(
            transaction: $transaction,
            bucket: Bucket::Want,
            subcategory: 'dining',
            source: ReviewSource::Manual,
            confidence: 100,
            explanation: 'Manually reviewed.',
        );

        $this->assertDatabaseHas('review_audits', [
            'transaction_id' => $transaction->id,
            'action' => 'review',
            'bucket' => 'want',
        ]);

        $undone = $this->service->undo($transaction->fresh());

        $this->assertNull($undone->reviewed_at);
        $this->assertSame(Bucket::Need, $undone->bucket);
        $this->assertSame('groceries', $undone->subcategory);
        $this->assertNull($undone->review_source);
        $this->assertNull($undone->confidence);
        $this->assertNull($undone->idempotency_key);

        $this->assertDatabaseHas('review_audits', [
            'transaction_id' => $transaction->id,
            'action' => 'undo',
            'source' => 'undo',
        ]);

        $undoAudit = ReviewAudit::query()
            ->where('transaction_id', $transaction->id)
            ->where('action', 'undo')
            ->latest('id')
            ->first();

        $this->assertNotNull($undoAudit);
        $this->assertSame('want', $undoAudit->previous_state['bucket'] ?? null);
        $this->assertSame('dining', $undoAudit->previous_state['subcategory'] ?? null);
    }

    #[TestDox('Undo on a factory-reviewed transaction without audit clears review without inventing a bucket')]
    public function test_undo_without_audit_clears_review_state(): void
    {
        $user = User::factory()->average()->create();
        $transaction = Transaction::factory()->for($user)->reviewed()->create([
            'bucket' => Bucket::Want,
            'subcategory' => 'shopping',
        ]);

        $undone = $this->service->undo($transaction);

        $this->assertNull($undone->reviewed_at);
        $this->assertNull($undone->bucket);
        $this->assertNull($undone->subcategory);
        $this->assertDatabaseHas('review_audits', [
            'transaction_id' => $transaction->id,
            'action' => 'undo',
        ]);
    }

    #[TestDox('Reviewing an already-reviewed transaction without an idempotency key is rejected')]
    public function test_review_rejects_already_reviewed_without_idempotency_key(): void
    {
        $user = User::factory()->average()->create();
        $transaction = Transaction::factory()->for($user)->reviewed()->create([
            'bucket' => Bucket::Need,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction is already reviewed.');

        $this->service->review(
            transaction: $transaction,
            bucket: Bucket::Want,
            subcategory: null,
            source: ReviewSource::Manual,
        );
    }

    #[TestDox('Undo rejects transactions that are not reviewed')]
    public function test_undo_rejects_unreviewed_transactions(): void
    {
        $user = User::factory()->average()->create();
        $transaction = Transaction::factory()->for($user)->unreviewed()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction is not reviewed.');

        $this->service->undo($transaction);
    }

    #[TestDox('Review with the same idempotency key returns the existing reviewed transaction')]
    public function test_review_idempotency_key_returns_existing_transaction(): void
    {
        $user = User::factory()->average()->create();
        $transaction = Transaction::factory()->for($user)->unreviewed()->create([
            'bucket' => null,
        ]);

        $first = $this->service->review(
            transaction: $transaction,
            bucket: Bucket::Need,
            subcategory: 'groceries',
            source: ReviewSource::Manual,
            confidence: 100,
            explanation: 'First review',
            idempotencyKey: 'review-key-1',
        );

        $second = $this->service->review(
            transaction: $transaction->fresh(),
            bucket: Bucket::Want,
            subcategory: 'dining',
            source: ReviewSource::Manual,
            confidence: 50,
            explanation: 'Should not apply',
            idempotencyKey: 'review-key-1',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(Bucket::Need, $second->bucket);
        $this->assertSame('groceries', $second->subcategory);
        $this->assertSame(1, ReviewAudit::query()->where('transaction_id', $transaction->id)->where('action', 'review')->count());
    }
}
