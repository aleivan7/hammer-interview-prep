<?php

namespace Tests\Feature;

use App\Enums\Bucket;
use App\Models\Account;
use App\Models\CategorizationRule;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\User;
use App\Support\CatalogNormalizer;
use Database\Seeders\CatalogSeeder;
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

    private Merchant $netflix;

    private Category $entertainment;

    private Category $housing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->withDemoUser();
        $this->seed(CatalogSeeder::class);
        $this->netflix = Merchant::query()->where('normalized_name', 'netflix')->firstOrFail();
        $this->entertainment = Category::query()
            ->system()
            ->where('bucket', Bucket::Want)
            ->where('normalized_name', 'entertainment')
            ->firstOrFail();
        $this->housing = Category::query()
            ->system()
            ->where('bucket', Bucket::Need)
            ->where('normalized_name', 'housing')
            ->firstOrFail();
    }

    #[TestDox('Creates, lists (priority-ordered), updates, and deletes categorization rules')]
    public function test_rules_crud_and_priority_ordering(): void
    {
        $account = Account::factory()->for($this->user)->create();
        $landlord = Merchant::query()->where('normalized_name', 'landlord llc')->firstOrFail();

        $higherPriority = CategorizationRule::factory()->for($this->user)->create([
            'name' => 'Later rule',
            'merchant_contains' => 'later',
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
            'priority' => 20,
        ]);

        $create = $this->postJson('/api/rules', [
            'name' => 'Landlord rent',
            'merchant_id' => $landlord->id,
            'account_id' => $account->id,
            'amount_cents_min' => 165_000,
            'amount_cents_max' => 165_000,
            'category_id' => $this->housing->id,
            'priority' => 5,
            'enabled' => true,
            'auto_review' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Landlord rent')
            ->assertJsonPath('data.merchant_id', $landlord->id)
            ->assertJsonPath('data.category_id', $this->housing->id)
            ->assertJsonPath('data.target_bucket', 'need')
            ->assertJsonPath('data.target_subcategory', 'housing')
            ->assertJsonPath('data.merchant_contains', 'landlord llc')
            ->assertJsonPath('data.priority', 5)
            ->assertJsonPath('data.id', $create->json('data.id'));

        $this->assertDatabaseHas('categorization_rules', [
            'id' => $create->json('data.id'),
            'user_id' => $this->user->id,
            'merchant_id' => $landlord->id,
            'category_id' => $this->housing->id,
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
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
            'amount_cents_min' => 5000,
            'amount_cents_max' => 1000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount_cents_max']);
    }

    #[TestDox('Rejects a target_bucket that conflicts with the selected category')]
    public function test_store_rejects_conflicting_target_bucket(): void
    {
        $this->postJson('/api/rules', [
            'name' => 'Conflict',
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
            'target_bucket' => Bucket::Need->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_bucket']);
    }

    #[TestDox('Rejects another persona’s custom category on create')]
    public function test_store_rejects_foreign_custom_category(): void
    {
        $other = User::factory()->reckless()->create();
        $foreignCategory = Category::factory()->create([
            'user_id' => $other->id,
            'bucket' => Bucket::Want,
            'name' => 'Foreign Dining',
            'normalized_name' => CatalogNormalizer::name('Foreign Dining'),
        ]);

        $this->postJson('/api/rules', [
            'name' => 'Bad category',
            'merchant_id' => $this->netflix->id,
            'category_id' => $foreignCategory->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    #[TestDox('Rejects archived categories on create')]
    public function test_store_rejects_archived_category(): void
    {
        $archived = Category::factory()->create([
            'user_id' => $this->user->id,
            'bucket' => Bucket::Want,
            'name' => 'Old Stuff',
            'archived_at' => now(),
        ]);

        $this->postJson('/api/rules', [
            'name' => 'Archived category',
            'merchant_id' => $this->netflix->id,
            'category_id' => $archived->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    #[TestDox('Rejects patching amount max below the rule’s existing amount min')]
    public function test_patch_rejects_amount_max_below_existing_min(): void
    {
        $rule = CategorizationRule::factory()->for($this->user)->create([
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
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
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
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
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
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
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
            'priority' => 1,
        ]);

        CategorizationRule::factory()->for($this->user)->create([
            'name' => 'Own rule',
            'merchant_contains' => 'own',
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
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
        $starbucks = Merchant::query()->where('normalized_name', 'starbucks')->firstOrFail();
        $dining = Category::query()
            ->system()
            ->where('bucket', Bucket::Want)
            ->where('normalized_name', 'dining')
            ->firstOrFail();

        $this->postJson('/api/rules', [
            'name' => 'Assigned rule',
            'merchant_id' => $starbucks->id,
            'category_id' => $dining->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Assigned rule')
            ->assertJsonPath('data.merchant_id', $starbucks->id)
            ->assertJsonPath('data.category_id', $dining->id)
            ->assertJsonPath('data.target_bucket', 'want')
            ->assertJsonPath('data.target_subcategory', 'dining');

        $this->assertDatabaseHas('categorization_rules', [
            'name' => 'Assigned rule',
            'user_id' => $this->user->id,
            'merchant_id' => $starbucks->id,
            'category_id' => $dining->id,
        ]);
    }

    #[TestDox('Create and update derive merchant_contains when explicitly null')]
    public function test_null_merchant_contains_is_derived_from_merchant(): void
    {
        $create = $this->postJson('/api/rules', [
            'name' => 'Derived merchant text',
            'merchant_id' => $this->netflix->id,
            'merchant_contains' => null,
            'category_id' => $this->entertainment->id,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.merchant_contains', 'netflix');

        $landlord = Merchant::query()->where('normalized_name', 'landlord llc')->firstOrFail();
        $this->patchJson("/api/rules/{$create->json('data.id')}", [
            'merchant_id' => $landlord->id,
            'merchant_contains' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.merchant_id', $landlord->id)
            ->assertJsonPath('data.merchant_contains', 'landlord llc');
    }

    #[TestDox('A user cannot update another user’s categorization rule')]
    public function test_user_cannot_update_another_users_rule(): void
    {
        $other = User::factory()->reckless()->create();
        $foreign = CategorizationRule::factory()->for($other)->create([
            'name' => 'Foreign rule',
            'merchant_contains' => 'foreign',
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
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
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
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
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
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
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
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

    #[TestDox('Partial rule updates re-derive targets from the existing linked category')]
    public function test_partial_update_rederives_targets_from_existing_category(): void
    {
        $rule = CategorizationRule::factory()->for($this->user)->create([
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'stale value',
            'enabled' => true,
        ]);

        $this->patchJson("/api/rules/{$rule->id}", [
            'enabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.target_bucket', 'want')
            ->assertJsonPath('data.target_subcategory', 'entertainment');
    }

    #[TestDox('Rejects target_subcategory that conflicts with a linked category')]
    public function test_update_rejects_conflicting_target_subcategory(): void
    {
        $rule = CategorizationRule::factory()->for($this->user)->create([
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'entertainment',
        ]);

        $this->patchJson("/api/rules/{$rule->id}", [
            'target_subcategory' => 'housing',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_subcategory']);

        $this->assertSame('entertainment', $rule->fresh()->target_subcategory);
    }

    #[TestDox('Updating category_id re-derives target_bucket and target_subcategory')]
    public function test_update_category_derives_legacy_target_fields(): void
    {
        $rule = CategorizationRule::factory()->for($this->user)->create([
            'merchant_id' => $this->netflix->id,
            'category_id' => $this->entertainment->id,
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'entertainment',
        ]);

        $this->patchJson("/api/rules/{$rule->id}", [
            'category_id' => $this->housing->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.category_id', $this->housing->id)
            ->assertJsonPath('data.target_bucket', 'need')
            ->assertJsonPath('data.target_subcategory', 'housing');
    }
}
