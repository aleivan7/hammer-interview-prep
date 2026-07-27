<?php

namespace Tests\Feature;

use App\Enums\Bucket;
use App\Models\Account;
use App\Models\CategorizationRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Categorization rules API: CRUD, priority ordering, and amount-bound validation.
 */
class CategorizationRuleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->withDemoUser();
    }

    #[TestDox('Creates, lists (priority-ordered), updates, and deletes categorization rules')]
    public function test_rules_crud_and_priority_ordering(): void
    {
        $account = Account::factory()->for($this->user)->create();

        $higherPriority = CategorizationRule::factory()->for($this->user)->create([
            'name' => 'Later rule',
            'merchant_contains' => 'later',
            'priority' => 20,
        ]);

        $create = $this->postJson('/api/rules', [
            'name' => 'Landlord rent',
            'merchant_contains' => 'property management',
            'account_id' => $account->id,
            'amount_cents_min' => 165_000,
            'amount_cents_max' => 165_000,
            'target_bucket' => 'need',
            'target_subcategory' => 'housing',
            'priority' => 5,
            'enabled' => true,
            'auto_review' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Landlord rent')
            ->assertJsonPath('data.target_bucket', 'need')
            ->assertJsonPath('data.priority', 5)
            ->assertJsonPath('data.id', $create->json('data.id'));

        $this->assertDatabaseHas('categorization_rules', [
            'id' => $create->json('data.id'),
            'user_id' => $this->user->id,
        ]);

        $index = $this->getJson('/api/rules');
        $index->assertOk()
            ->assertJsonPath('data.0.id', $create->json('data.id'))
            ->assertJsonPath('data.1.id', $higherPriority->id);

        $ruleId = $create->json('data.id');

        $this->patchJson("/api/rules/{$ruleId}", [
            'enabled' => false,
            'priority' => 8,
        ])
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.priority', 8);

        $this->deleteJson("/api/rules/{$ruleId}")->assertNoContent();

        $this->assertDatabaseMissing('categorization_rules', [
            'id' => $ruleId,
        ]);
    }

    #[TestDox('Rejects creating a rule when amount max is below amount min')]
    public function test_store_rejects_invalid_amount_bounds(): void
    {
        $this->postJson('/api/rules', [
            'name' => 'Broken',
            'merchant_contains' => 'x',
            'target_bucket' => Bucket::Want->value,
            'amount_cents_min' => 5000,
            'amount_cents_max' => 1000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount_cents_max']);
    }

    #[TestDox('Rejects patching amount max below the rule’s existing amount min')]
    public function test_patch_rejects_amount_max_below_existing_min(): void
    {
        $rule = CategorizationRule::factory()->for($this->user)->create([
            'amount_cents_min' => 5000,
            'amount_cents_max' => 10_000,
        ]);

        $this->patchJson("/api/rules/{$rule->id}", [
            'amount_cents_max' => 1000,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_cents_max']);

        $this->assertDatabaseHas('categorization_rules', [
            'id' => $rule->id,
            'amount_cents_min' => 5000,
            'amount_cents_max' => 10_000,
        ]);
    }

    #[TestDox('Rejects patching amount min above the rule’s existing amount max')]
    public function test_patch_rejects_amount_min_above_existing_max(): void
    {
        $rule = CategorizationRule::factory()->for($this->user)->create([
            'amount_cents_min' => 1000,
            'amount_cents_max' => 5000,
        ]);

        $this->patchJson("/api/rules/{$rule->id}", [
            'amount_cents_min' => 9000,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_cents_min']);

        $this->assertDatabaseHas('categorization_rules', [
            'id' => $rule->id,
            'amount_cents_min' => 1000,
            'amount_cents_max' => 5000,
        ]);
    }

    #[TestDox('Rejects patching both amount bounds when max is below min')]
    public function test_patch_rejects_both_amount_bounds_when_max_below_min(): void
    {
        $rule = CategorizationRule::factory()->for($this->user)->create([
            'amount_cents_min' => 1000,
            'amount_cents_max' => 5000,
        ]);

        $this->patchJson("/api/rules/{$rule->id}", [
            'amount_cents_min' => 8000,
            'amount_cents_max' => 2000,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_cents_max']);

        $this->assertDatabaseHas('categorization_rules', [
            'id' => $rule->id,
            'amount_cents_min' => 1000,
            'amount_cents_max' => 5000,
        ]);
    }

    #[TestDox('Rules are scoped to the selected demo user')]
    public function test_rules_are_scoped_to_selected_user(): void
    {
        $other = User::factory()->reckless()->create();
        CategorizationRule::factory()->for($other)->create([
            'name' => 'Foreign rule',
            'merchant_contains' => 'foreign',
            'priority' => 1,
        ]);

        CategorizationRule::factory()->for($this->user)->create([
            'name' => 'Own rule',
            'merchant_contains' => 'own',
            'priority' => 2,
        ]);

        $this->getJson('/api/rules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Own rule');
    }

    #[TestDox('Creating a rule assigns the selected demo user')]
    public function test_creating_a_rule_assigns_selected_user(): void
    {
        $this->postJson('/api/rules', [
            'name' => 'Assigned rule',
            'merchant_contains' => 'coffee',
            'target_bucket' => 'want',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Assigned rule');

        $this->assertDatabaseHas('categorization_rules', [
            'name' => 'Assigned rule',
            'user_id' => $this->user->id,
        ]);
    }

    #[TestDox('A user cannot update another user’s categorization rule')]
    public function test_user_cannot_update_another_users_rule(): void
    {
        $other = User::factory()->reckless()->create();
        $foreign = CategorizationRule::factory()->for($other)->create([
            'name' => 'Foreign rule',
            'merchant_contains' => 'foreign',
            'enabled' => true,
        ]);

        $this->patchJson("/api/rules/{$foreign->id}", [
            'enabled' => false,
            'name' => 'Hijacked',
        ])->assertForbidden();

        $this->assertDatabaseHas('categorization_rules', [
            'id' => $foreign->id,
            'name' => 'Foreign rule',
            'enabled' => true,
            'user_id' => $other->id,
        ]);
    }

    #[TestDox('A user cannot delete another user’s categorization rule')]
    public function test_user_cannot_delete_another_users_rule(): void
    {
        $other = User::factory()->reckless()->create();
        $foreign = CategorizationRule::factory()->for($other)->create([
            'name' => 'Foreign keep',
            'merchant_contains' => 'keep',
        ]);

        $this->deleteJson("/api/rules/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseHas('categorization_rules', [
            'id' => $foreign->id,
            'name' => 'Foreign keep',
            'user_id' => $other->id,
        ]);
    }

    #[TestDox('Creating a rule rejects another user’s account')]
    public function test_creating_a_rule_rejects_foreign_account(): void
    {
        $other = User::factory()->reckless()->create();
        $foreignAccount = Account::factory()->for($other)->create();

        $this->postJson('/api/rules', [
            'name' => 'Bad account rule',
            'merchant_contains' => 'coffee',
            'target_bucket' => 'want',
            'account_id' => $foreignAccount->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_id']);
    }

    #[TestDox('Updating a rule rejects another user’s account')]
    public function test_updating_a_rule_rejects_foreign_account(): void
    {
        $other = User::factory()->reckless()->create();
        $foreignAccount = Account::factory()->for($other)->create();
        $rule = CategorizationRule::factory()->for($this->user)->create([
            'account_id' => null,
        ]);

        $this->patchJson("/api/rules/{$rule->id}", [
            'account_id' => $foreignAccount->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_id']);

        $this->assertDatabaseHas('categorization_rules', [
            'id' => $rule->id,
            'account_id' => null,
        ]);
    }
}
