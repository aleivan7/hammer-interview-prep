<?php

namespace Tests\Unit;

use App\Enums\Bucket;
use App\Enums\ReviewSource;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\ReviewAudit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    #[TestDox('Undo restores an audited canonical merchant link without re-resolving it')]
    public function test_undo_restores_audited_merchant_link(): void
    {
        $user = User::factory()->average()->create();
        $netflix = Merchant::query()->where('normalized_name', 'netflix')->firstOrFail();
        $transaction = Transaction::factory()->for($user)->unreviewed()->create([
            'merchant' => 'Mystery Descriptor',
            'raw_merchant_descriptor' => 'Mystery Descriptor',
            'bucket' => Bucket::Want,
        ]);
        DB::table('transactions')->where('id', $transaction->id)->update([
            'merchant_id' => $netflix->id,
        ]);
        $transaction->refresh();

        $this->service->review(
            transaction: $transaction,
            bucket: Bucket::Want,
            subcategory: null,
            source: ReviewSource::Manual,
        );
        $transaction->refresh()->update(['merchant' => 'Another Unknown Descriptor']);
        $this->assertNull($transaction->fresh()->merchant_id);

        $undone = $this->service->undo($transaction->fresh());

        $this->assertSame('Mystery Descriptor', $undone->merchant);
        $this->assertSame('Mystery Descriptor', $undone->raw_merchant_descriptor);
        $this->assertSame($netflix->id, $undone->merchant_id);
    }

    #[TestDox('Undo restores a category link using the category’s current fields')]
    public function test_undo_restores_category_link_with_current_category_fields(): void
    {
        $user = User::factory()->average()->create();
        $category = Category::factory()->for($user)->create([
            'bucket' => Bucket::Want,
            'name' => 'Original Treats',
        ]);
        $transaction = Transaction::factory()->for($user)->unreviewed()->create([
            'category_id' => $category->id,
        ]);

        $this->service->review(
            transaction: $transaction,
            bucket: Bucket::Need,
            subcategory: null,
            source: ReviewSource::Manual,
            categoryId: null,
        );
        $category->update([
            'bucket' => Bucket::Savings,
            'name' => 'Renamed Later',
        ]);

        $undone = $this->service->undo($transaction->fresh());

        $this->assertSame(Bucket::Savings, $undone->bucket);
        $this->assertSame('Renamed Later', $undone->subcategory);
        $this->assertSame($category->id, $undone->category_id);
    }

    #[TestDox('Review uses the category display name consistently in the transaction and audit')]
    public function test_review_canonicalizes_category_fields_for_transaction_and_audit(): void
    {
        $user = User::factory()->average()->create();
        $category = Category::factory()->for($user)->create([
            'bucket' => Bucket::Want,
            'name' => 'Coffee Treats',
        ]);
        $transaction = Transaction::factory()->for($user)->unreviewed()->create();

        $reviewed = $this->service->review(
            transaction: $transaction,
            bucket: Bucket::Need,
            subcategory: 'coffee treats',
            source: ReviewSource::Rule,
            categoryId: $category->id,
        );

        $audit = ReviewAudit::query()
            ->where('transaction_id', $transaction->id)
            ->where('action', 'review')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(Bucket::Want, $reviewed->bucket);
        $this->assertSame('Coffee Treats', $reviewed->subcategory);
        $this->assertSame($category->id, $reviewed->category_id);
        $this->assertSame(Bucket::Want, $audit->bucket);
        $this->assertSame('Coffee Treats', $audit->subcategory);
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

    #[TestDox('Idempotency keys are scoped per user so personas can reuse the same key')]
    public function test_review_idempotency_key_is_scoped_per_user(): void
    {
        $userA = User::factory()->average()->create();
        $userB = User::factory()->reckless()->create();
        $transactionA = Transaction::factory()->for($userA)->unreviewed()->create();
        $transactionB = Transaction::factory()->for($userB)->unreviewed()->create();

        $reviewedA = $this->service->review(
            transaction: $transactionA,
            bucket: Bucket::Need,
            subcategory: 'groceries',
            source: ReviewSource::Manual,
            confidence: 100,
            explanation: 'Persona A review',
            idempotencyKey: 'shared-key',
        );

        $reviewedB = $this->service->review(
            transaction: $transactionB,
            bucket: Bucket::Want,
            subcategory: 'dining',
            source: ReviewSource::Manual,
            confidence: 100,
            explanation: 'Persona B review',
            idempotencyKey: 'shared-key',
        );

        $this->assertSame($transactionA->id, $reviewedA->id);
        $this->assertSame($transactionB->id, $reviewedB->id);
        $this->assertSame(Bucket::Need, $reviewedA->bucket);
        $this->assertSame(Bucket::Want, $reviewedB->bucket);
        $this->assertSame('shared-key', $reviewedA->idempotency_key);
        $this->assertSame('shared-key', $reviewedB->idempotency_key);
        $this->assertSame(1, ReviewAudit::query()->where('transaction_id', $transactionA->id)->where('action', 'review')->count());
        $this->assertSame(1, ReviewAudit::query()->where('transaction_id', $transactionB->id)->where('action', 'review')->count());
    }

    #[TestDox('A new unused idempotency key re-reviews an already-reviewed transaction')]
    public function test_review_with_new_idempotency_key_updates_already_reviewed_transaction(): void
    {
        $user = User::factory()->average()->create();
        $transaction = Transaction::factory()->for($user)->unreviewed()->create([
            'bucket' => null,
        ]);

        $this->service->review(
            transaction: $transaction,
            bucket: Bucket::Need,
            subcategory: 'groceries',
            source: ReviewSource::Manual,
            confidence: 100,
            explanation: 'First review',
            idempotencyKey: 'key-1',
        );

        $updated = $this->service->review(
            transaction: $transaction->fresh(),
            bucket: Bucket::Want,
            subcategory: 'dining',
            source: ReviewSource::Manual,
            confidence: 90,
            explanation: 'Second review with new key',
            idempotencyKey: 'key-2',
        );

        $this->assertSame(Bucket::Want, $updated->bucket);
        $this->assertSame('dining', $updated->subcategory);
        $this->assertSame('key-2', $updated->idempotency_key);
        $this->assertSame(2, ReviewAudit::query()->where('transaction_id', $transaction->id)->where('action', 'review')->count());
    }
}
