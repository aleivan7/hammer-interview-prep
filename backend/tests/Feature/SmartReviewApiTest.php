<?php

namespace Tests\Feature;

use App\Enums\Bucket;
use App\Models\CategorizationRule;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_smart_review_applies_confident_matches_and_skips_uncertain(): void
    {
        CategorizationRule::factory()->create([
            'name' => 'Netflix want',
            'merchant_contains' => 'netflix',
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'entertainment',
            'priority' => 10,
            'auto_review' => true,
        ]);

        $confident = Transaction::factory()->unreviewed()->create([
            'merchant' => 'Netflix',
            'transaction_date' => '2026-07-10',
        ]);

        $uncertain = Transaction::factory()->unreviewed()->create([
            'merchant' => 'Unknown Boutique XYZ',
            'transaction_date' => '2026-07-11',
        ]);

        $response = $this->postJson('/api/smart-review', [
            'batch_key' => 'batch-demo-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.batch_key', 'batch-demo-1')
            ->assertJsonPath('data.applied_count', 1)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.applied.0.id', $confident->id)
            ->assertJsonPath('data.skipped.0.id', $uncertain->id);

        $this->assertDatabaseHas('transactions', [
            'id' => $confident->id,
            'bucket' => 'want',
            'idempotency_key' => 'smart-review:batch-demo-1:'.$confident->id,
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $uncertain->id,
            'reviewed_at' => null,
        ]);
    }

    public function test_smart_review_retry_with_same_batch_key_is_idempotent(): void
    {
        CategorizationRule::factory()->create([
            'merchant_contains' => 'heb',
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'groceries',
            'auto_review' => true,
        ]);

        $transaction = Transaction::factory()->unreviewed()->create([
            'merchant' => 'HEB Market',
            'transaction_date' => '2026-07-12',
        ]);

        $first = $this->postJson('/api/smart-review', ['batch_key' => 'retry-key']);
        $first->assertOk()
            ->assertJsonPath('data.applied_count', 1)
            ->assertJsonPath('data.applied.0.id', $transaction->id);

        $second = $this->postJson('/api/smart-review', ['batch_key' => 'retry-key']);
        $second->assertOk()
            ->assertJsonPath('data.batch_key', 'retry-key')
            ->assertJsonPath('data.applied_count', 1)
            ->assertJsonPath('data.applied.0.id', $transaction->id)
            ->assertJsonPath('data.skipped_count', 0);

        $this->assertSame(1, Transaction::query()->whereNotNull('reviewed_at')->count());
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'idempotency_key' => 'smart-review:retry-key:'.$transaction->id,
        ]);
    }

    public function test_smart_review_rejects_like_wildcards_in_batch_key(): void
    {
        foreach (['batch%', 'batch_key'] as $batchKey) {
            $this->postJson('/api/smart-review', ['batch_key' => $batchKey])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['batch_key']);
        }
    }

    public function test_rule_precedence_lower_priority_wins(): void
    {
        CategorizationRule::factory()->create([
            'name' => 'Generic amazon want',
            'merchant_contains' => 'amazon',
            'target_bucket' => Bucket::Want,
            'priority' => 50,
            'auto_review' => true,
        ]);

        CategorizationRule::factory()->create([
            'name' => 'Amazon groceries need',
            'merchant_contains' => 'amazon',
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'groceries',
            'priority' => 5,
            'auto_review' => true,
        ]);

        $transaction = Transaction::factory()->unreviewed()->create([
            'merchant' => 'Amazon Fresh',
        ]);

        $this->getJson("/api/transactions/{$transaction->id}/suggestion")
            ->assertOk()
            ->assertJsonPath('data.bucket', 'need')
            ->assertJsonPath('data.subcategory', 'groceries')
            ->assertJsonPath('data.source', 'rule');
    }
}
