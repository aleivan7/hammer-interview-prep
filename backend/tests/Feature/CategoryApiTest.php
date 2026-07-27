<?php

namespace Tests\Feature;

use App\Enums\Bucket;
use App\Models\CategorizationRule;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->withDemoUser();
    }

    #[TestDox('Lists shared system categories and only the selected persona custom categories')]
    public function test_lists_system_and_own_custom_categories_without_foreign_custom_categories(): void
    {
        $this->seed(CatalogSeeder::class);

        Category::factory()->for($this->user)->create([
            'bucket' => Bucket::Want,
            'name' => 'Coffee Treats',
            'sort_order' => 5,
        ]);

        $other = User::factory()->reckless()->create();
        Category::factory()->for($other)->create([
            'bucket' => Bucket::Want,
            'name' => 'Secret Splurge',
            'sort_order' => 1,
        ]);

        Category::factory()->for($this->user)->archived()->create([
            'bucket' => Bucket::Need,
            'name' => 'Old Archived',
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'user_id',
                        'bucket',
                        'name',
                        'normalized_name',
                        'sort_order',
                        'is_system',
                        'archived_at',
                    ],
                ],
            ]);

        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Entertainment'));
        $this->assertTrue($names->contains('Coffee Treats'));
        $this->assertFalse($names->contains('Secret Splurge'));
        $this->assertFalse($names->contains('Old Archived'));

        $this->assertTrue(
            collect($response->json('data'))
                ->contains(fn (array $row): bool => $row['name'] === 'Entertainment' && $row['is_system'] === true),
        );
    }

    #[TestDox('Creates, renames, and archives custom categories for the selected persona')]
    public function test_custom_category_create_rename_and_archive(): void
    {
        $create = $this->postJson('/api/categories', [
            'name' => 'Date Night',
            'bucket' => 'want',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Date Night')
            ->assertJsonPath('data.bucket', 'want')
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.user_id', $this->user->id);

        $id = $create->json('data.id');

        $this->patchJson("/api/categories/{$id}", [
            'name' => 'Date Nights',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Date Nights')
            ->assertJsonPath('data.normalized_name', 'date nights');

        $this->deleteJson("/api/categories/{$id}")->assertNoContent();

        $this->assertNotNull(Category::query()->find($id)?->archived_at);
        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Date Nights']);
    }

    #[TestDox('Rejects duplicate custom category names that differ only by case')]
    public function test_rejects_duplicate_normalized_names(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->postJson('/api/categories', [
            'name' => 'entertainment',
            'bucket' => 'want',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    #[TestDox('Archived custom category names can be reused without losing history')]
    public function test_archived_custom_category_name_can_be_reused(): void
    {
        $original = Category::factory()->for($this->user)->create([
            'name' => 'Coffee Runs',
            'bucket' => Bucket::Want,
        ]);
        $this->deleteJson("/api/categories/{$original->id}")->assertNoContent();

        $replacement = $this->postJson('/api/categories', [
            'name' => '  coffee runs  ',
            'bucket' => 'want',
        ]);

        $replacement->assertCreated()
            ->assertJsonPath('data.name', 'coffee runs')
            ->assertJsonPath('data.bucket', 'want');
        $this->assertNotSame($original->id, $replacement->json('data.id'));
        $this->assertNotNull($original->fresh()->archived_at);
    }

    #[TestDox('Moving a category rejects a duplicate name in the destination bucket')]
    public function test_bucket_only_update_rejects_duplicate_in_destination_bucket(): void
    {
        $category = Category::factory()->for($this->user)->create([
            'name' => 'Groceries',
            'bucket' => Bucket::Want,
        ]);

        $this->patchJson("/api/categories/{$category->id}", [
            'bucket' => 'need',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertSame(Bucket::Want, $category->fresh()->bucket);
    }

    #[TestDox('Renaming or moving a category synchronizes linked transactions and rules')]
    public function test_category_update_synchronizes_linked_records(): void
    {
        $category = Category::factory()->for($this->user)->create([
            'name' => 'Coffee Runs',
            'bucket' => Bucket::Want,
        ]);
        $transaction = Transaction::factory()->for($this->user)->create([
            'category_id' => $category->id,
        ]);
        $rule = CategorizationRule::factory()->for($this->user)->create([
            'category_id' => $category->id,
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'coffee runs',
        ]);
        $other = User::factory()->reckless()->create();
        $foreignTransaction = Transaction::factory()->for($other)->create([
            'category_id' => $category->id,
        ]);
        $foreignRule = CategorizationRule::factory()->for($other)->create([
            'category_id' => $category->id,
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'coffee runs',
        ]);

        $this->patchJson("/api/categories/{$category->id}", [
            'name' => 'Work Meals',
            'bucket' => 'need',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Work Meals')
            ->assertJsonPath('data.bucket', 'need');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'category_id' => $category->id,
            'bucket' => 'need',
            'subcategory' => 'Work Meals',
        ]);
        $this->assertDatabaseHas('categorization_rules', [
            'id' => $rule->id,
            'category_id' => $category->id,
            'target_bucket' => 'need',
            'target_subcategory' => 'work meals',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $foreignTransaction->id,
            'user_id' => $other->id,
            'bucket' => 'want',
            'subcategory' => 'Coffee Runs',
        ]);
        $this->assertDatabaseHas('categorization_rules', [
            'id' => $foreignRule->id,
            'user_id' => $other->id,
            'target_bucket' => 'want',
            'target_subcategory' => 'coffee runs',
        ]);
    }

    #[TestDox('Unarchiving a category rejects a duplicate active name')]
    public function test_unarchive_rejects_duplicate_active_name(): void
    {
        $category = Category::factory()->for($this->user)->archived()->create([
            'name' => 'Dining',
            'bucket' => Bucket::Want,
        ]);

        $this->patchJson("/api/categories/{$category->id}", [
            'archived' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertNotNull($category->fresh()->archived_at);
    }

    #[TestDox('Rejects renaming or archiving system categories')]
    public function test_rejects_mutating_system_categories(): void
    {
        $this->seed(CatalogSeeder::class);
        $system = Category::query()->system()->where('normalized_name', 'dining')->firstOrFail();

        $this->patchJson("/api/categories/{$system->id}", [
            'name' => 'Dining Out',
        ])->assertForbidden();

        $this->deleteJson("/api/categories/{$system->id}")->assertNotFound();

        $this->assertDatabaseHas('categories', [
            'id' => $system->id,
            'name' => 'Dining',
            'archived_at' => null,
        ]);
    }

    #[TestDox('Rejects mutating another persona custom category')]
    public function test_rejects_foreign_custom_category_mutation(): void
    {
        $other = User::factory()->reckless()->create();
        $foreign = Category::factory()->for($other)->create([
            'name' => 'Foreign Custom',
            'bucket' => Bucket::Want,
        ]);

        $this->patchJson("/api/categories/{$foreign->id}", [
            'name' => 'Hijacked',
        ])->assertForbidden();

        $this->deleteJson("/api/categories/{$foreign->id}")->assertNotFound();
    }

    #[TestDox('System category seeds are idempotent and unique by bucket and normalized name')]
    public function test_system_category_seeds_are_idempotent(): void
    {
        $this->seed(CatalogSeeder::class);
        $firstCount = Category::query()->system()->count();

        $this->seed(CatalogSeeder::class);

        $this->assertSame($firstCount, Category::query()->system()->count());
        $this->assertGreaterThan(0, $firstCount);
        $this->assertDatabaseHas('categories', [
            'user_id' => null,
            'bucket' => Bucket::Want->value,
            'normalized_name' => 'entertainment',
            'name' => 'Entertainment',
        ]);
    }

    #[TestDox('Category list requires a selected demo user')]
    public function test_category_list_requires_demo_user(): void
    {
        $this->flushHeaders();

        $this->getJson('/api/categories')->assertUnauthorized();
    }
}
