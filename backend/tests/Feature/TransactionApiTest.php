<?php

namespace Tests\Feature;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * These tests describe the finished API behavior.
 * They should fail until Alejandro completes the TODO sections.
 */
class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_unreviewed_transactions(): void
    {
        $unreviewed = Transaction::factory()->unreviewed()->create([
            'merchant' => 'Unreviewed Store',
            'transaction_date' => '2026-07-10',
        ]);

        Transaction::factory()->reviewed()->create([
            'merchant' => 'Reviewed Store',
            'transaction_date' => '2026-07-09',
        ]);

        $response = $this->getJson('/api/transactions');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $unreviewed->id);
        $response->assertJsonMissing([
            'merchant' => 'Reviewed Store',
        ]);
    }

    public function test_index_returns_transactions_oldest_first(): void
    {
        $newer = Transaction::factory()->unreviewed()->create([
            'merchant' => 'Newer Merchant',
            'transaction_date' => '2026-07-20',
        ]);

        $older = Transaction::factory()->unreviewed()->create([
            'merchant' => 'Older Merchant',
            'transaction_date' => '2026-07-10',
        ]);

        $response = $this->getJson('/api/transactions');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $older->id);
        $response->assertJsonPath('data.1.id', $newer->id);
    }

    public function test_patch_with_valid_category_updates_category(): void
    {
        $transaction = Transaction::factory()->unreviewed()->create([
            'category' => null,
        ]);

        $response = $this->patchJson("/api/transactions/{$transaction->id}", [
            'category' => 'need',
            'reviewed' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.category', 'need');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'category' => 'need',
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
            'category' => 'need',
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
        $response->assertJsonValidationErrors(['category']);
    }

    public function test_patch_returns_transaction_resource_json_shape(): void
    {
        $transaction = Transaction::factory()->unreviewed()->create([
            'merchant' => 'HEB',
            'amount' => '84.23',
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
                'category',
                'transaction_date',
                'reviewed',
            ],
        ]);

        // After the TODOs are done, the values should match the update.
        $response->assertJsonPath('data.id', $transaction->id);
        $response->assertJsonPath('data.merchant', 'HEB');
        $response->assertJsonPath('data.amount', '84.23');
        $response->assertJsonPath('data.category', 'need');
        $response->assertJsonPath('data.transaction_date', '2026-07-20');
        $response->assertJsonPath('data.reviewed', true);
    }
}
