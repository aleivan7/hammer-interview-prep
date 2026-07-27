<?php

namespace Tests\Unit;

use App\Enums\Bucket;
use App\Enums\ReviewSource;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\CategorizationRule;
use App\Models\Transaction;
use App\Services\RulesAndHeuristicsCategorizer;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->categorizer = app(RulesAndHeuristicsCategorizer::class);
    }

    #[TestDox('Skips disabled rules and falls through to heuristics')]
    public function test_disabled_rules_are_ignored(): void
    {
        CategorizationRule::factory()->create([
            'name' => 'Disabled HEB need',
            'merchant_contains' => 'heb',
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'shopping',
            'enabled' => false,
            'auto_review' => true,
            'priority' => 1,
        ]);

        $transaction = Transaction::factory()->unreviewed()->create([
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
        $checking = Account::factory()->create();
        $savings = Account::factory()->create();

        CategorizationRule::factory()->create([
            'name' => 'Checking-only rent',
            'merchant_contains' => 'landlord',
            'account_id' => $checking->id,
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'housing',
            'auto_review' => true,
            'priority' => 1,
        ]);

        $mismatch = Transaction::factory()->unreviewed()->create([
            'merchant' => 'Landlord LLC',
            'account_id' => $savings->id,
            'kind' => TransactionKind::Expense,
        ]);

        $match = Transaction::factory()->unreviewed()->create([
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
            'merchant' => 'Property Management',
            'amount_cents' => 164_999,
        ]);
        $exact = Transaction::factory()->unreviewed()->create([
            'merchant' => 'Property Management',
            'amount_cents' => 165_000,
        ]);
        $tooHigh = Transaction::factory()->unreviewed()->create([
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
            'merchant_contains' => 'HeB',
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'groceries',
            'auto_review' => true,
        ]);

        $transaction = Transaction::factory()->unreviewed()->create([
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
            'merchant' => 'Internal Transfer',
            'kind' => TransactionKind::Transfer,
        ]);
        $income = Transaction::factory()->unreviewed()->create([
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
}
