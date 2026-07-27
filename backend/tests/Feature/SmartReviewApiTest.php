<?php

namespace Tests\Feature;

use App\Enums\Bucket;
use App\Models\CategorizationRule;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Smart Review API: confident auto-apply, idempotent batches, and rule precedence.
 */
class SmartReviewApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->withDemoUser();
    }

    #[TestDox('Auto-reviews confident rule matches and leaves uncertain merchants unreviewed')]
    public function test_smart_review_applies_confident_matches_and_skips_uncertain(): void
    {
        CategorizationRule::factory()->for($this->user)->create([
            'name' => 'Netflix want',
            'merchant_contains' => 'netflix',
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'entertainment',
            'priority' => 10,
            'auto_review' => true,
        ]);

        $confident = Transaction::factory()->for($this->user)->unreviewed()->create([
            'merchant' => 'Netflix',
            'transaction_date' => '2026-07-10',
        ]);

        $uncertain = Transaction::factory()->for($this->user)->unreviewed()->create([
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
            'idempotency_key' => 'smart-review:'.$this->user->id.':batch-demo-1:'.$confident->id,
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $uncertain->id,
            'reviewed_at' => null,
        ]);
    }

    #[TestDox('Retrying Smart Review with the same batch key does not double-apply reviews')]
    public function test_smart_review_retry_with_same_batch_key_is_idempotent(): void
    {
        CategorizationRule::factory()->for($this->user)->create([
            'merchant_contains' => 'heb',
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'groceries',
            'auto_review' => true,
        ]);

        $transaction = Transaction::factory()->for($this->user)->unreviewed()->create([
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

        $this->assertSame(1, Transaction::query()->forUser($this->user)->whereNotNull('reviewed_at')->count());
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'idempotency_key' => 'smart-review:'.$this->user->id.':retry-key:'.$transaction->id,
        ]);
    }

    #[TestDox('Rejects batch keys that contain SQL LIKE wildcards')]
    public function test_smart_review_rejects_like_wildcards_in_batch_key(): void
    {
        foreach (['batch%', 'batch_key'] as $batchKey) {
            $this->postJson('/api/smart-review', ['batch_key' => $batchKey])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['batch_key']);
        }
    }

    #[TestDox('Suggestion endpoint prefers the lower-priority (more specific) matching rule')]
    public function test_rule_precedence_lower_priority_wins(): void
    {
        CategorizationRule::factory()->for($this->user)->create([
            'name' => 'Generic amazon want',
            'merchant_contains' => 'amazon',
            'target_bucket' => Bucket::Want,
            'priority' => 50,
            'auto_review' => true,
        ]);

        CategorizationRule::factory()->for($this->user)->create([
            'name' => 'Amazon groceries need',
            'merchant_contains' => 'amazon',
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'groceries',
            'priority' => 5,
            'auto_review' => true,
        ]);

        $transaction = Transaction::factory()->for($this->user)->unreviewed()->create([
            'merchant' => 'Amazon Fresh',
        ]);

        $this->getJson("/api/transactions/{$transaction->id}/suggestion")
            ->assertOk()
            ->assertJsonPath('data.bucket', 'need')
            ->assertJsonPath('data.subcategory', 'groceries')
            ->assertJsonPath('data.source', 'rule');
    }

    #[TestDox('Smart Review only evaluates the selected user’s rules and transactions')]
    public function test_smart_review_is_scoped_to_selected_user(): void
    {
        $other = User::factory()->reckless()->create();

        CategorizationRule::factory()->for($other)->create([
            'merchant_contains' => 'netflix',
            'target_bucket' => Bucket::Want,
            'auto_review' => true,
        ]);

        $foreign = Transaction::factory()->for($other)->unreviewed()->create([
            'merchant' => 'Netflix',
        ]);

        $own = Transaction::factory()->for($this->user)->unreviewed()->create([
            'merchant' => 'Unknown Local Shop',
        ]);

        $this->postJson('/api/smart-review', ['batch_key' => 'scoped'])
            ->assertOk()
            ->assertJsonPath('data.applied_count', 0)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.skipped.0.id', $own->id);

        $this->assertDatabaseHas('transactions', [
            'id' => $foreign->id,
            'reviewed_at' => null,
        ]);
    }

    #[TestDox('Smart Review can re-apply after undo when the same batch key is reused')]
    public function test_smart_review_reapplies_after_undo_with_same_batch_key(): void
    {
        CategorizationRule::factory()->for($this->user)->create([
            'merchant_contains' => 'heb',
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'groceries',
            'auto_review' => true,
        ]);

        $transaction = Transaction::factory()->for($this->user)->unreviewed()->create([
            'merchant' => 'HEB Market',
            'transaction_date' => '2026-07-12',
        ]);

        $this->postJson('/api/smart-review', ['batch_key' => 'retry-after-undo'])
            ->assertOk()
            ->assertJsonPath('data.applied_count', 1)
            ->assertJsonPath('data.applied.0.id', $transaction->id);

        $this->postJson("/api/transactions/{$transaction->id}/undo")
            ->assertOk()
            ->assertJsonPath('data.reviewed', false);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'reviewed_at' => null,
            'idempotency_key' => null,
        ]);

        $this->postJson('/api/smart-review', ['batch_key' => 'retry-after-undo'])
            ->assertOk()
            ->assertJsonPath('data.applied_count', 1)
            ->assertJsonPath('data.applied.0.id', $transaction->id)
            ->assertJsonPath('data.skipped_count', 0);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'bucket' => 'need',
            'idempotency_key' => 'smart-review:'.$this->user->id.':retry-after-undo:'.$transaction->id,
        ]);
        $this->assertNotNull($transaction->fresh()->reviewed_at);
    }

    #[TestDox('Suggestion and Smart Review ignore another user’s matching auto-review rules')]
    public function test_foreign_user_rules_do_not_categorize_selected_user_transactions(): void
    {
        $other = User::factory()->reckless()->create();

        CategorizationRule::factory()->for($other)->create([
            'name' => 'Foreign boutique want',
            'merchant_contains' => 'unique boutique xyz',
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'shopping',
            'auto_review' => true,
            'priority' => 1,
        ]);

        CategorizationRule::factory()->for($this->user)->create([
            'name' => 'Own boutique need',
            'merchant_contains' => 'unique boutique xyz',
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'household',
            'auto_review' => true,
            'priority' => 1,
        ]);

        $transaction = Transaction::factory()->for($this->user)->unreviewed()->create([
            'merchant' => 'Unique Boutique XYZ',
            'transaction_date' => '2026-07-14',
        ]);

        $this->getJson("/api/transactions/{$transaction->id}/suggestion")
            ->assertOk()
            ->assertJsonPath('data.source', 'rule')
            ->assertJsonPath('data.bucket', 'need')
            ->assertJsonPath('data.subcategory', 'household')
            ->assertJsonPath('data.auto_review', true);

        // Disable the owned rule so only the foreign rule remains as a candidate.
        CategorizationRule::query()->forUser($this->user)->update(['enabled' => false]);

        $this->getJson("/api/transactions/{$transaction->id}/suggestion")
            ->assertOk()
            ->assertJsonPath('data.source', 'heuristic')
            ->assertJsonPath('data.bucket', null)
            ->assertJsonPath('data.confidence', 0)
            ->assertJsonPath('data.auto_review', false);

        $this->postJson('/api/smart-review', ['batch_key' => 'foreign-rule-isolation'])
            ->assertOk()
            ->assertJsonPath('data.applied_count', 0)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.skipped.0.id', $transaction->id);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'reviewed_at' => null,
            'bucket' => null,
        ]);
    }
}
