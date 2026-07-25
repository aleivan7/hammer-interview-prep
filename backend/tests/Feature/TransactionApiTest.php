<?php

namespace Tests\Feature;

use App\Enums\Bucket;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_unreviewed_transactions_when_requested(): void
    {
        $unreviewed = Transaction::factory()->unreviewed()->create([
            'merchant' => 'Unreviewed Store',
            'transaction_date' => '2026-07-10',
        ]);

        Transaction::factory()->reviewed()->create([
            'merchant' => 'Reviewed Store',
            'transaction_date' => '2026-07-09',
        ]);

        $response = $this->getJson('/api/transactions?unreviewed_only=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $unreviewed->id);
        $response->assertJsonMissing([
            'merchant' => 'Reviewed Store',
        ]);
    }

    public function test_index_returns_transactions_oldest_first_for_review_queue(): void
    {
        $newer = Transaction::factory()->unreviewed()->create([
            'merchant' => 'Newer Merchant',
            'transaction_date' => '2026-07-20',
        ]);

        $older = Transaction::factory()->unreviewed()->create([
            'merchant' => 'Older Merchant',
            'transaction_date' => '2026-07-10',
        ]);

        $response = $this->getJson('/api/transactions?queue=review');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $older->id);
        $response->assertJsonPath('data.1.id', $newer->id);
    }

    public function test_patch_with_valid_category_updates_bucket(): void
    {
        $transaction = Transaction::factory()->unreviewed()->create([
            'bucket' => null,
        ]);

        $response = $this->patchJson("/api/transactions/{$transaction->id}", [
            'category' => 'need',
            'reviewed' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.bucket', 'need');
        $response->assertJsonPath('data.category', 'need');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'bucket' => 'need',
        ]);
    }

    public function test_patch_with_reviewed_true_sets_reviewed_at(): void
    {
        $transaction = Transaction::factory()->unreviewed()->create();

        $response = $this->patchJson("/api/transactions/{$transaction->id}", [
            'category' => 'want',
            'reviewed' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.reviewed', true);

        $transaction->refresh();
        $this->assertNotNull($transaction->reviewed_at);
    }

    public function test_patch_with_reviewed_false_clears_reviewed_at(): void
    {
        $transaction = Transaction::factory()->reviewed()->create([
            'bucket' => Bucket::Need,
        ]);

        $response = $this->patchJson("/api/transactions/{$transaction->id}", [
            'category' => 'need',
            'reviewed' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.reviewed', false);

        $transaction->refresh();
        $this->assertNull($transaction->reviewed_at);
    }

    public function test_patch_with_unsupported_category_returns_422(): void
    {
        $transaction = Transaction::factory()->unreviewed()->create();

        $response = $this->patchJson("/api/transactions/{$transaction->id}", [
            'category' => 'entertainment',
            'reviewed' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category']);
    }

    public function test_patch_with_missing_category_returns_422(): void
    {
        $transaction = Transaction::factory()->unreviewed()->create();

        $response = $this->patchJson("/api/transactions/{$transaction->id}", [
            'reviewed' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_patch_returns_transaction_resource_json_shape(): void
    {
        $transaction = Transaction::factory()->unreviewed()->create([
            'merchant' => 'HEB',
            'amount_cents' => 8423,
            'transaction_date' => '2026-07-20',
        ]);

        $response = $this->patchJson("/api/transactions/{$transaction->id}", [
            'category' => 'need',
            'reviewed' => true,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'merchant',
                'amount',
                'amount_cents',
                'bucket',
                'category',
                'transaction_date',
                'reviewed',
            ],
        ]);

        $response->assertJsonPath('data.id', $transaction->id);
        $response->assertJsonPath('data.merchant', 'HEB');
        $response->assertJsonPath('data.amount', '84.23');
        $response->assertJsonPath('data.amount_cents', 8423);
        $response->assertJsonPath('data.bucket', 'need');
        $response->assertJsonPath('data.transaction_date', '2026-07-20');
        $response->assertJsonPath('data.reviewed', true);
    }

    public function test_store_creates_manual_transaction(): void
    {
        $response = $this->postJson('/api/transactions', [
            'merchant' => 'Corner Market',
            'amount_cents' => 1250,
            'kind' => 'expense',
            'transaction_date' => '2026-07-22',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.merchant', 'Corner Market')
            ->assertJsonPath('data.amount', '12.50');

        $this->assertDatabaseHas('transactions', [
            'merchant' => 'Corner Market',
            'amount_cents' => 1250,
        ]);
    }

    public function test_store_rejects_reviewed_transaction_without_bucket(): void
    {
        $this->postJson('/api/transactions', [
            'merchant' => 'Corner Market',
            'amount_cents' => 1250,
            'kind' => 'expense',
            'transaction_date' => '2026-07-22',
            'reviewed' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bucket']);
    }

    public function test_patch_persists_edits_when_transaction_remains_unreviewed(): void
    {
        $transaction = Transaction::factory()->unreviewed()->create([
            'merchant' => 'Original Merchant',
        ]);

        $this->patchJson("/api/transactions/{$transaction->id}", [
            'merchant' => 'Updated Merchant',
            'reviewed' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.merchant', 'Updated Merchant')
            ->assertJsonPath('data.reviewed', false);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'merchant' => 'Updated Merchant',
            'reviewed_at' => null,
        ]);
    }

    public function test_patch_persists_edits_while_undoing_review(): void
    {
        $transaction = Transaction::factory()->reviewed()->create([
            'merchant' => 'Original Merchant',
            'bucket' => Bucket::Need,
        ]);

        $this->patchJson("/api/transactions/{$transaction->id}", [
            'merchant' => 'Updated Merchant',
            'reviewed' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.merchant', 'Updated Merchant')
            ->assertJsonPath('data.reviewed', false);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'merchant' => 'Updated Merchant',
            'reviewed_at' => null,
        ]);
    }

    public function test_patch_updates_an_already_reviewed_transaction(): void
    {
        $transaction = Transaction::factory()->reviewed()->create([
            'merchant' => 'Original Merchant',
            'bucket' => Bucket::Need,
        ]);

        $this->patchJson("/api/transactions/{$transaction->id}", [
            'merchant' => 'Updated Merchant',
            'bucket' => 'want',
            'reviewed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.merchant', 'Updated Merchant')
            ->assertJsonPath('data.bucket', 'want')
            ->assertJsonPath('data.reviewed', true);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'merchant' => 'Updated Merchant',
            'bucket' => 'want',
        ]);
    }

    public function test_patch_accepts_savings_bucket_and_debt_savings_alias(): void
    {
        $savings = Transaction::factory()->unreviewed()->create();
        $legacy = Transaction::factory()->unreviewed()->create();

        $this->patchJson("/api/transactions/{$savings->id}", [
            'bucket' => 'savings',
            'reviewed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.bucket', 'savings')
            ->assertJsonPath('data.category', 'savings');

        $this->patchJson("/api/transactions/{$legacy->id}", [
            'category' => 'debt_savings',
            'reviewed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.bucket', 'savings')
            ->assertJsonPath('data.category', 'savings');
    }

    public function test_undo_endpoint_clears_review(): void
    {
        $transaction = Transaction::factory()->reviewed()->create([
            'bucket' => Bucket::Want,
        ]);

        $this->postJson("/api/transactions/{$transaction->id}/undo")
            ->assertOk()
            ->assertJsonPath('data.reviewed', false);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'reviewed_at' => null,
        ]);
    }
}
