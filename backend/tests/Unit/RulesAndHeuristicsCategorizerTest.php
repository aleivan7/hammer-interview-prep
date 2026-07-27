<?php

namespace Tests\Unit;

use App\Enums\Bucket;
use App\Enums\MatchStrategy;
use App\Enums\ReviewSource;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\CategorizationRule;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantAlias;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RulesAndHeuristicsCategorizer;
use App\Support\CatalogNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * RulesAndHeuristicsCategorizer: rule filters, precedence fallbacks, and heuristics.
 */
class RulesAndHeuristicsCategorizerTest extends TestCase
{
    use RefreshDatabase;

    private RulesAndHeuristicsCategorizer $categorizer;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categorizer = app(RulesAndHeuristicsCategorizer::class);
        $this->user = User::factory()->create();
    }

    #[TestDox('Skips disabled rules and falls through to heuristics')]
    public function test_disabled_rules_are_ignored(): void
    {
        CategorizationRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Disabled HEB need',
            'merchant_contains' => 'heb',
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'shopping',
            'enabled' => false,
            'auto_review' => true,
            'priority' => 1,
        ]);

        $transaction = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'HEB Market',
            'kind' => TransactionKind::Expense,
        ]);

        $result = $this->categorizer->categorize($transaction);

        $this->assertSame(ReviewSource::Heuristic, $result->source);
        $this->assertSame(Bucket::Need, $result->bucket);
        $this->assertSame('groceries', $result->subcategory);
        $this->assertSame(86, $result->confidence);
        $this->assertTrue($result->isConfident());
    }

    #[TestDox('Ignores account-scoped rules when the transaction account does not match')]
    public function test_account_scoped_rules_require_matching_account(): void
    {
        $checking = Account::factory()->create(['user_id' => $this->user->id]);
        $savings = Account::factory()->create(['user_id' => $this->user->id]);

        CategorizationRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Checking-only rent',
            'merchant_contains' => 'landlord',
            'account_id' => $checking->id,
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'housing',
            'auto_review' => true,
            'priority' => 1,
        ]);

        $mismatch = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'Landlord LLC',
            'account_id' => $savings->id,
            'kind' => TransactionKind::Expense,
        ]);

        $match = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'Landlord LLC',
            'account_id' => $checking->id,
            'kind' => TransactionKind::Expense,
        ]);

        $miss = $this->categorizer->categorize($mismatch);
        $hit = $this->categorizer->categorize($match);

        $this->assertFalse($miss->isConfident());
        $this->assertNull($miss->bucket);

        $this->assertSame(ReviewSource::Rule, $hit->source);
        $this->assertSame(Bucket::Need, $hit->bucket);
        $this->assertSame('housing', $hit->subcategory);
        $this->assertTrue($hit->isConfident());
    }

    #[TestDox('Applies amount min/max bounds before accepting a rule match')]
    public function test_amount_bounds_filter_rule_matches(): void
    {
        CategorizationRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Exact rent',
            'merchant_contains' => 'property',
            'amount_cents_min' => 165_000,
            'amount_cents_max' => 165_000,
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'housing',
            'auto_review' => true,
            'priority' => 1,
        ]);

        $tooLow = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'Property Management',
            'amount_cents' => 164_999,
        ]);
        $exact = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'Property Management',
            'amount_cents' => 165_000,
        ]);
        $tooHigh = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'Property Management',
            'amount_cents' => 165_001,
        ]);

        $this->assertFalse($this->categorizer->categorize($tooLow)->isConfident());
        $this->assertFalse($this->categorizer->categorize($tooHigh)->isConfident());

        $hit = $this->categorizer->categorize($exact);
        $this->assertSame(ReviewSource::Rule, $hit->source);
        $this->assertSame(Bucket::Need, $hit->bucket);
        $this->assertTrue($hit->isConfident());
    }

    #[TestDox('Matches merchant_contains case-insensitively')]
    public function test_merchant_contains_is_case_insensitive(): void
    {
        CategorizationRule::factory()->create([
            'user_id' => $this->user->id,
            'merchant_contains' => 'HeB',
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'groceries',
            'auto_review' => true,
        ]);

        $transaction = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'heb MARKET',
        ]);

        $result = $this->categorizer->categorize($transaction);

        $this->assertSame(ReviewSource::Rule, $result->source);
        $this->assertSame(Bucket::Need, $result->bucket);
        $this->assertSame(95, $result->confidence);
    }

    #[TestDox('Auto-reviews transfers and income via heuristics')]
    public function test_transfer_and_income_heuristics_auto_review(): void
    {
        $transfer = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'Internal Transfer',
            'kind' => TransactionKind::Transfer,
        ]);
        $income = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'Acme Payroll',
            'kind' => TransactionKind::Income,
        ]);

        $transferResult = $this->categorizer->categorize($transfer);
        $incomeResult = $this->categorizer->categorize($income);

        $this->assertSame(ReviewSource::Heuristic, $transferResult->source);
        $this->assertSame(Bucket::Savings, $transferResult->bucket);
        $this->assertSame('transfer', $transferResult->subcategory);
        $this->assertTrue($transferResult->isConfident());

        $this->assertSame(ReviewSource::Heuristic, $incomeResult->source);
        $this->assertSame(Bucket::Savings, $incomeResult->bucket);
        $this->assertSame('income', $incomeResult->subcategory);
        $this->assertTrue($incomeResult->isConfident());
    }

    #[TestDox('Low-confidence dining heuristic suggests a bucket but is not auto-reviewable')]
    public function test_dining_heuristic_is_below_auto_review_threshold(): void
    {
        $transaction = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'Chipotle Downtown',
            'kind' => TransactionKind::Expense,
        ]);

        $result = $this->categorizer->categorize($transaction);

        $this->assertSame(ReviewSource::Heuristic, $result->source);
        $this->assertSame(Bucket::Want, $result->bucket);
        $this->assertSame('dining', $result->subcategory);
        $this->assertSame(80, $result->confidence);
        $this->assertFalse($result->autoReview);
        $this->assertFalse($result->isConfident());
    }

    #[TestDox('Ignores another user’s matching rule when categorizing a transaction')]
    public function test_foreign_user_rules_are_ignored(): void
    {
        $other = User::factory()->create();

        CategorizationRule::factory()->create([
            'user_id' => $other->id,
            'name' => 'Foreign boutique want',
            'merchant_contains' => 'unique boutique xyz',
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'shopping',
            'auto_review' => true,
            'priority' => 1,
        ]);

        $transaction = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'Unique Boutique XYZ',
            'kind' => TransactionKind::Expense,
        ]);

        $result = $this->categorizer->categorize($transaction);

        $this->assertSame(ReviewSource::Heuristic, $result->source);
        $this->assertNull($result->bucket);
        $this->assertSame(0, $result->confidence);
        $this->assertFalse($result->autoReview);
        $this->assertNull($result->ruleId);
    }

    #[TestDox('When priorities tie, the lower rule id wins')]
    public function test_same_priority_prefers_lower_rule_id(): void
    {
        $first = CategorizationRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Earlier boutique need',
            'merchant_contains' => 'priority boutique',
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'household',
            'auto_review' => true,
            'priority' => 5,
        ]);

        CategorizationRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Later boutique want',
            'merchant_contains' => 'priority boutique',
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'shopping',
            'auto_review' => true,
            'priority' => 5,
        ]);

        $transaction = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'Priority Boutique',
            'kind' => TransactionKind::Expense,
        ]);

        $result = $this->categorizer->categorize($transaction);

        $this->assertSame(ReviewSource::Rule, $result->source);
        $this->assertSame(Bucket::Need, $result->bucket);
        $this->assertSame('household', $result->subcategory);
        $this->assertSame($first->id, $result->ruleId);
    }

    #[TestDox('Canonical merchant rules match via MerchantResolver aliases')]
    public function test_canonical_merchant_rules_match_via_aliases(): void
    {
        $merchant = Merchant::factory()->create([
            'name' => 'Netflix',
            'normalized_name' => CatalogNormalizer::name('Netflix'),
        ]);

        MerchantAlias::factory()->create([
            'merchant_id' => $merchant->id,
            'pattern' => 'NETFLIX',
            'normalized_pattern' => CatalogNormalizer::descriptor('NETFLIX'),
            'match_strategy' => MatchStrategy::Prefix,
            'priority' => 20,
            'enabled' => true,
        ]);

        $category = Category::factory()->system()->create([
            'bucket' => Bucket::Want,
            'name' => 'Entertainment',
            'normalized_name' => 'entertainment',
        ]);

        $rule = CategorizationRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Netflix entertainment',
            'merchant_contains' => 'netflix',
            'merchant_id' => $merchant->id,
            'category_id' => $category->id,
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'entertainment',
            'auto_review' => true,
            'priority' => 1,
        ]);

        $transaction = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'NETFLIX.COM 408724',
            'raw_merchant_descriptor' => 'NETFLIX.COM 408724',
            'kind' => TransactionKind::Expense,
        ]);

        $result = $this->categorizer->categorize($transaction);

        $this->assertSame(ReviewSource::Rule, $result->source);
        $this->assertSame(Bucket::Want, $result->bucket);
        $this->assertSame('entertainment', $result->subcategory);
        $this->assertSame($category->id, $result->categoryId);
        $this->assertSame($rule->id, $result->ruleId);
        $this->assertTrue($result->isConfident());
        $this->assertStringContainsString('Netflix', $result->explanation);
    }

    #[TestDox('Canonical merchant rules run before heuristics')]
    public function test_canonical_merchant_rules_run_before_heuristics(): void
    {
        $merchant = Merchant::factory()->create([
            'name' => 'Chipotle',
            'normalized_name' => CatalogNormalizer::name('Chipotle'),
        ]);

        MerchantAlias::factory()->create([
            'merchant_id' => $merchant->id,
            'pattern' => 'CHIPOTLE',
            'normalized_pattern' => CatalogNormalizer::descriptor('CHIPOTLE'),
            'match_strategy' => MatchStrategy::Prefix,
            'priority' => 10,
            'enabled' => true,
        ]);

        $category = Category::factory()->system()->create([
            'bucket' => Bucket::Need,
            'name' => 'Groceries',
            'normalized_name' => 'groceries',
        ]);

        CategorizationRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Chipotle as groceries',
            'merchant_id' => $merchant->id,
            'category_id' => $category->id,
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'groceries',
            'auto_review' => true,
            'priority' => 10,
        ]);

        $transaction = Transaction::factory()->unreviewed()->create([
            'user_id' => $this->user->id,
            'merchant' => 'Chipotle Downtown',
            'raw_merchant_descriptor' => 'Chipotle Downtown',
            'kind' => TransactionKind::Expense,
        ]);

        $result = $this->categorizer->categorize($transaction);

        $this->assertSame(ReviewSource::Rule, $result->source);
        $this->assertSame(Bucket::Need, $result->bucket);
        $this->assertSame('groceries', $result->subcategory);
        $this->assertSame($category->id, $result->categoryId);
        $this->assertTrue($result->isConfident());
    }
}
