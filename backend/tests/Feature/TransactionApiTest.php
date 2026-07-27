<?php

namespace Tests\Feature;

use App\Enums\Bucket;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Transactions API: review queue, create/edit/undo, and reviewed-bucket invariants.
 */
class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->withDemoUser();
    }

    #[TestDox('Index with unreviewed_only returns only transactions still awaiting review')]
    public function test_index_returns_only_unreviewed_transactions_when_requested(): void
    {
        $unreviewed = Transaction::factory()->for($this->user)->unreviewed()->create([
            'merchant' => 'Unreviewed Store',
            'transaction_date' => '2026-07-10',
        ]);

        Transaction::factory()->for($this->user)->reviewed()->create([
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

    #[TestDox('Review queue index returns unreviewed transactions oldest-first')]
    public function test_index_returns_transactions_oldest_first_for_review_queue(): void
    {
        $newer = Transaction::factory()->for($this->user)->unreviewed()->create([
            'merchant' => 'Newer Merchant',
            'transaction_date' => '2026-07-20',
        ]);

        $older = Transaction::factory()->for($this->user)->unreviewed()->create([
            'merchant' => 'Older Merchant',
            'transaction_date' => '2026-07-10',
        ]);

        $response = $this->getJson('/api/transactions?queue=review');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $older->id);
        $response->assertJsonPath('data.1.id', $newer->id);
    }

    #[TestDox('Patch with a valid category sets the transaction bucket')]
    public function test_patch_with_valid_category_updates_bucket(): void
    {
        $transaction = Transaction::factory()->for($this->user)->unreviewed()->create([
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

    #[TestDox('Patch with reviewed true stamps reviewed_at')]
    public function test_patch_with_reviewed_true_sets_reviewed_at(): void
    {
        $transaction = Transaction::factory()->for($this->user)->unreviewed()->create();

        $response = $this->patchJson("/api/transactions/{$transaction->id}", [
            'category' => 'want',
            'reviewed' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.reviewed', true);

        $transaction->refresh();
        $this->assertNotNull($transaction->reviewed_at);
    }

    #[TestDox('Patch with reviewed false clears reviewed_at')]
    public function test_patch_with_reviewed_false_clears_reviewed_at(): void
    {
        $transaction = Transaction::factory()->for($this->user)->reviewed()->create([
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

    #[TestDox('Patch with an unsupported category returns 422')]
    public function test_patch_with_unsupported_category_returns_422(): void
    {
        $transaction = Transaction::factory()->for($this->user)->unreviewed()->create();

        $response = $this->patchJson("/api/transactions/{$transaction->id}", [
            'category' => 'entertainment',
            'reviewed' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category']);
    }

    #[TestDox('Patch marking unreviewed (uncategorized) as reviewed without a bucket returns 422')]
    public function test_patch_with_missing_category_returns_422(): void
    {
        $transaction = Transaction::factory()->for($this->user)->unreviewed()->create();

        $response = $this->patchJson("/api/transactions/{$transaction->id}", [
            'reviewed' => true,
        ]);

        $response->assertStatus(422);
    }

    #[TestDox('Patch can mark a pre-categorized transaction reviewed without resending the bucket')]
    public function test_patch_can_review_a_pre_categorized_transaction_without_repeating_bucket(): void
    {
        $transaction = Transaction::factory()->for($this->user)->unreviewed()->create([
            'bucket' => Bucket::Need,
        ]);

        $this->patchJson("/api/transactions/{$transaction->id}", [
            'reviewed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.bucket', 'need')
            ->assertJsonPath('data.reviewed', true);
    }

    #[TestDox('Patch response matches the Transaction resource JSON shape')]
    public function test_patch_returns_transaction_resource_json_shape(): void
    {
        $transaction = Transaction::factory()->for($this->user)->unreviewed()->create([
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

    #[TestDox('Store creates a manual unreviewed transaction')]
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
            'user_id' => $this->user->id,
        ]);
    }

    #[TestDox('Store rejects creating a reviewed transaction without a bucket')]
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

    #[TestDox('Patch persists field edits when the transaction stays unreviewed')]
    public function test_patch_persists_edits_when_transaction_remains_unreviewed(): void
    {
        $transaction = Transaction::factory()->for($this->user)->unreviewed()->create([
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

    #[TestDox('Patch persists field edits while clearing review state')]
    public function test_patch_persists_edits_while_undoing_review(): void
    {
        $transaction = Transaction::factory()->for($this->user)->reviewed()->create([
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

    #[TestDox('Patch updates merchant and bucket on an already-reviewed transaction')]
    public function test_patch_updates_an_already_reviewed_transaction(): void
    {
        $transaction = Transaction::factory()->for($this->user)->reviewed()->create([
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

    #[TestDox('Patch rejects clearing the bucket while the transaction remains reviewed')]
    public function test_patch_rejects_clearing_bucket_while_transaction_remains_reviewed(): void
    {
        $transaction = Transaction::factory()->for($this->user)->reviewed()->create([
            'bucket' => Bucket::Need,
        ]);

        $this->patchJson("/api/transactions/{$transaction->id}", [
            'bucket' => null,
            'reviewed' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bucket']);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'bucket' => 'need',
        ]);
    }

    #[TestDox('Patch accepts savings bucket and maps the legacy debt_savings category alias')]
    public function test_patch_accepts_savings_bucket_and_debt_savings_alias(): void
    {
        $savings = Transaction::factory()->for($this->user)->unreviewed()->create();
        $legacy = Transaction::factory()->for($this->user)->unreviewed()->create();

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

    #[TestDox('Undo endpoint clears review state on a reviewed transaction')]
    public function test_undo_endpoint_clears_review(): void
    {
        $transaction = Transaction::factory()->for($this->user)->reviewed()->create([
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

    #[TestDox('Undo after a manual review restores the prior bucket from the review audit')]
    public function test_undo_after_manual_review_restores_previous_bucket(): void
    {
        $transaction = Transaction::factory()->for($this->user)->unreviewed()->create([
            'bucket' => Bucket::Need,
            'subcategory' => 'groceries',
            'merchant' => 'HEB',
        ]);

        $this->patchJson("/api/transactions/{$transaction->id}", [
            'bucket' => 'want',
            'subcategory' => 'dining',
            'reviewed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.bucket', 'want')
            ->assertJsonPath('data.reviewed', true);

        $this->postJson("/api/transactions/{$transaction->id}/undo")
            ->assertOk()
            ->assertJsonPath('data.reviewed', false)
            ->assertJsonPath('data.bucket', 'need');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'bucket' => 'need',
            'subcategory' => 'groceries',
            'reviewed_at' => null,
        ]);

        $this->assertDatabaseHas('review_audits', [
            'transaction_id' => $transaction->id,
            'action' => 'review',
            'bucket' => 'want',
        ]);
        $this->assertDatabaseHas('review_audits', [
            'transaction_id' => $transaction->id,
            'action' => 'undo',
        ]);
    }

    #[TestDox('Transaction listing belongs only to the selected user')]
    public function test_transaction_listing_belongs_only_to_selected_user(): void
    {
        $other = User::factory()->reckless()->create();

        Transaction::factory()->for($this->user)->unreviewed()->create([
            'merchant' => 'Own Merchant',
        ]);
        Transaction::factory()->for($other)->unreviewed()->create([
            'merchant' => 'Foreign Merchant',
        ]);

        $this->getJson('/api/transactions?paginate=false')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.merchant', 'Own Merchant');
    }

    #[TestDox('Index filters by reviewed state, bucket, account, and merchant search')]
    public function test_index_filters_by_reviewed_bucket_account_and_search(): void
    {
        $checking = Account::factory()->for($this->user)->create(['name' => 'Checking']);
        $savings = Account::factory()->for($this->user)->create(['name' => 'Savings']);
        $foreign = User::factory()->reckless()->create();
        $foreignAccount = Account::factory()->for($foreign)->create();

        Transaction::factory()->for($this->user)->reviewed()->create([
            'account_id' => $checking->id,
            'merchant' => 'HEB Market',
            'bucket' => Bucket::Need,
            'transaction_date' => '2026-07-10',
        ]);
        Transaction::factory()->for($this->user)->reviewed()->create([
            'account_id' => $savings->id,
            'merchant' => 'Netflix',
            'bucket' => Bucket::Want,
            'transaction_date' => '2026-07-11',
        ]);
        Transaction::factory()->for($this->user)->unreviewed()->create([
            'account_id' => $checking->id,
            'merchant' => 'Mystery Shop',
            'transaction_date' => '2026-07-12',
        ]);
        Transaction::factory()->for($foreign)->reviewed()->create([
            'account_id' => $foreignAccount->id,
            'merchant' => 'HEB Market',
            'bucket' => Bucket::Need,
        ]);

        $this->getJson('/api/transactions?reviewed=1&paginate=false')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/transactions?reviewed=0&paginate=false')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.merchant', 'Mystery Shop');

        $this->getJson('/api/transactions?bucket=need&paginate=false')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.merchant', 'HEB Market');

        $this->getJson('/api/transactions?account_id='.$checking->id.'&paginate=false')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/transactions?account_id='.$foreignAccount->id.'&paginate=false')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/transactions?search=heb&paginate=false')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.merchant', 'HEB Market');
    }

    #[TestDox('Default index pagination returns page meta with 25-item pages')]
    public function test_index_default_pagination_includes_meta(): void
    {
        Transaction::factory()->for($this->user)->count(26)->unreviewed()->create();

        $response = $this->getJson('/api/transactions');

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 26)
            ->assertJsonPath('meta.current_page', 1);
    }

    #[TestDox('A user cannot update another user’s transaction')]
    public function test_user_cannot_update_another_users_transaction(): void
    {
        $other = User::factory()->reckless()->create();
        $foreign = Transaction::factory()->for($other)->unreviewed()->create([
            'merchant' => 'Foreign Merchant',
        ]);

        $this->patchJson("/api/transactions/{$foreign->id}", [
            'merchant' => 'Hijacked',
            'reviewed' => false,
        ])->assertForbidden();

        $this->assertDatabaseHas('transactions', [
            'id' => $foreign->id,
            'merchant' => 'Foreign Merchant',
        ]);
    }

    #[TestDox('A user cannot undo another user’s transaction')]
    public function test_user_cannot_undo_another_users_transaction(): void
    {
        $other = User::factory()->reckless()->create();
        $foreign = Transaction::factory()->for($other)->reviewed()->create([
            'bucket' => Bucket::Want,
        ]);

        $this->postJson("/api/transactions/{$foreign->id}/undo")->assertNotFound();

        $this->assertNotNull($foreign->fresh()->reviewed_at);
    }

    #[TestDox('A user cannot access another user’s transaction suggestion')]
    public function test_user_cannot_access_another_users_suggestion(): void
    {
        $other = User::factory()->reckless()->create();
        $foreign = Transaction::factory()->for($other)->unreviewed()->create([
            'merchant' => 'Netflix',
        ]);

        $this->getJson("/api/transactions/{$foreign->id}/suggestion")->assertNotFound();
    }

    #[TestDox('Creating a transaction assigns the selected user')]
    public function test_creating_a_transaction_assigns_selected_user(): void
    {
        $account = Account::factory()->for($this->user)->create();

        $this->postJson('/api/transactions', [
            'merchant' => 'Owned Create',
            'amount_cents' => 2500,
            'kind' => 'expense',
            'transaction_date' => '2026-07-22',
            'account_id' => $account->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.merchant', 'Owned Create');

        $this->assertDatabaseHas('transactions', [
            'merchant' => 'Owned Create',
            'user_id' => $this->user->id,
            'account_id' => $account->id,
        ]);
    }

    #[TestDox('Creating a transaction rejects another user’s account')]
    public function test_creating_a_transaction_rejects_foreign_account(): void
    {
        $other = User::factory()->reckless()->create();
        $foreignAccount = Account::factory()->for($other)->create();

        $this->postJson('/api/transactions', [
            'merchant' => 'Bad Account',
            'amount_cents' => 2500,
            'kind' => 'expense',
            'transaction_date' => '2026-07-22',
            'account_id' => $foreignAccount->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_id']);
    }

    #[TestDox('Updating a transaction rejects another user’s account')]
    public function test_updating_a_transaction_rejects_foreign_account(): void
    {
        $other = User::factory()->reckless()->create();
        $ownAccount = Account::factory()->for($this->user)->create();
        $foreignAccount = Account::factory()->for($other)->create();
        $transaction = Transaction::factory()->for($this->user)->unreviewed()->create([
            'account_id' => $ownAccount->id,
        ]);

        $this->patchJson("/api/transactions/{$transaction->id}", [
            'account_id' => $foreignAccount->id,
            'reviewed' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_id']);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'account_id' => $ownAccount->id,
        ]);
    }
}
