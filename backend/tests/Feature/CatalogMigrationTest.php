<?php

namespace Tests\Feature;

use App\Enums\Bucket;
use App\Models\CategorizationRule;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class CatalogMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Migrations install reference catalog data before application seeders run')]
    public function test_migrations_install_reference_catalog_data(): void
    {
        $this->assertDatabaseHas('categories', [
            'user_id' => null,
            'bucket' => 'want',
            'normalized_name' => 'dining',
        ]);
        $this->assertDatabaseHas('merchants', [
            'normalized_name' => 'starbucks',
        ]);
        $this->assertDatabaseHas('merchant_aliases', [
            'normalized_pattern' => 'STARBUCKS',
            'match_strategy' => 'prefix',
            'enabled' => true,
        ]);
    }

    #[TestDox('Catalog backfills link legacy transactions and rules using migration-installed data')]
    public function test_catalog_backfills_link_legacy_rows(): void
    {
        $user = User::factory()->average()->create();
        $transaction = Transaction::factory()->for($user)->create([
            'merchant' => 'STARBUCKS 123',
            'raw_merchant_descriptor' => null,
            'bucket' => Bucket::Want,
            'subcategory' => 'Dining',
        ]);
        $rule = CategorizationRule::factory()->for($user)->create([
            'merchant_contains' => 'Starbucks',
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'dining',
        ]);

        DB::table('transactions')->where('id', $transaction->id)->update([
            'raw_merchant_descriptor' => null,
            'merchant_id' => null,
            'category_id' => null,
        ]);
        DB::table('categorization_rules')->where('id', $rule->id)->update([
            'merchant_id' => null,
            'category_id' => null,
        ]);

        $transactionBackfill = require database_path('migrations/2026_07_27_211456_backfill_transaction_catalog_links.php');
        $transactionBackfill->up();
        $ruleBackfill = require database_path('migrations/2026_07_27_211855_backfill_categorization_rule_catalog_links.php');
        $ruleBackfill->up();

        $starbucksId = DB::table('merchants')->where('normalized_name', 'starbucks')->value('id');
        $diningId = DB::table('categories')
            ->whereNull('user_id')
            ->where('bucket', 'want')
            ->where('normalized_name', 'dining')
            ->value('id');

        $this->assertNotNull($starbucksId);
        $this->assertNotNull($diningId);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'raw_merchant_descriptor' => 'STARBUCKS 123',
            'merchant_id' => $starbucksId,
            'category_id' => $diningId,
        ]);
        $this->assertDatabaseHas('categorization_rules', [
            'id' => $rule->id,
            'merchant_id' => $starbucksId,
            'category_id' => $diningId,
        ]);
    }
}
