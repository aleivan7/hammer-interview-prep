<?php

namespace Tests\Feature;

use App\Enums\Bucket;
use App\Models\Account;
use App\Models\CategorizationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Categorization rules API: CRUD, priority ordering, and amount-bound validation.
 */
class CategorizationRuleApiTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Creates, lists (priority-ordered), updates, and deletes categorization rules')]
    public function test_rules_crud_and_priority_ordering(): void
    {
        $account = Account::factory()->create();

        $higherPriority = CategorizationRule::factory()->create([
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
            ->assertJsonPath('data.priority', 5);

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
        $rule = CategorizationRule::factory()->create([
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
}
