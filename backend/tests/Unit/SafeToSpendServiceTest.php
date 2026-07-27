<?php

namespace Tests\Unit;

use App\Enums\Bucket;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\FinancialPlan;
use App\Models\PlannedCashFlow;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SafeToSpendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Safe-to-spend service: integer balances/targets, transfer exclusion, refunds.
 */
class SafeToSpendServiceTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Computes safe-to-spend from integer account balances, income, bills, and savings target')]
    public function test_cent_math_uses_integer_balances_and_targets(): void
    {
        $user = User::factory()->average()->create();

        FinancialPlan::factory()->for($user)->create([
            'needs_percent' => 50,
            'wants_percent' => 30,
            'savings_percent' => 20,
            'safety_buffer_cents' => 25_000,
            'monthly_income_cents' => 520_000,
        ]);

        Account::factory()->for($user)->create(['balance_cents' => 100_000]);
        Account::factory()->for($user)->create(['balance_cents' => 50_050]);

        PlannedCashFlow::factory()->for($user)->create([
            'name' => 'Paycheck',
            'kind' => 'income',
            'amount_cents' => 260_000,
            'due_on' => '2026-07-28',
        ]);

        PlannedCashFlow::factory()->for($user)->bill()->create([
            'name' => 'Rent',
            'amount_cents' => 165_000,
            'due_on' => '2026-07-30',
        ]);

        $asOf = Carbon::parse('2026-07-25');
        $result = app(SafeToSpendService::class)->forUser($user, $asOf);

        $this->assertSame(116_050, $result['safe_to_spend_cents']);
        $this->assertSame('1160.50', $result['amount']);
        $this->assertSame(150_050, $result['breakdown']['available_cash_cents']);
        $this->assertSame(104_000, $result['bucket_targets']['savings']);
    }

    #[TestDox('Excludes transfer transactions from bucket spending actuals')]
    public function test_transfers_are_excluded_from_bucket_spending(): void
    {
        $user = User::factory()->average()->create();

        FinancialPlan::factory()->for($user)->create([
            'monthly_income_cents' => 100_000,
            'savings_percent' => 20,
            'safety_buffer_cents' => 0,
        ]);
        Account::factory()->for($user)->create(['balance_cents' => 0]);

        Transaction::factory()->for($user)->reviewed()->create([
            'kind' => TransactionKind::Transfer,
            'bucket' => Bucket::Savings,
            'amount_cents' => 50_000,
            'transaction_date' => '2026-07-10',
        ]);

        Transaction::factory()->for($user)->reviewed()->create([
            'kind' => TransactionKind::Expense,
            'bucket' => Bucket::Need,
            'amount_cents' => 12_34,
            'transaction_date' => '2026-07-11',
        ]);

        $result = app(SafeToSpendService::class)->forUser($user, Carbon::parse('2026-07-15'));

        $this->assertSame(12_34, $result['bucket_actuals']['need']);
        $this->assertSame(0, $result['bucket_actuals']['savings']);
    }

    #[TestDox('Subtracts refunds from the matching bucket’s spending actuals')]
    public function test_refunds_reduce_bucket_actuals(): void
    {
        $user = User::factory()->average()->create();

        FinancialPlan::factory()->for($user)->create([
            'monthly_income_cents' => 100_000,
            'savings_percent' => 0,
            'safety_buffer_cents' => 0,
        ]);
        Account::factory()->for($user)->create(['balance_cents' => 0]);

        Transaction::factory()->for($user)->reviewed()->create([
            'kind' => TransactionKind::Expense,
            'bucket' => Bucket::Want,
            'amount_cents' => 10_000,
            'transaction_date' => '2026-07-05',
        ]);

        Transaction::factory()->for($user)->reviewed()->create([
            'kind' => TransactionKind::Refund,
            'bucket' => Bucket::Want,
            'amount_cents' => 2_500,
            'transaction_date' => '2026-07-06',
        ]);

        $result = app(SafeToSpendService::class)->forUser($user, Carbon::parse('2026-07-15'));

        $this->assertSame(7_500, $result['bucket_actuals']['want']);
    }

    #[TestDox('Safe-to-spend uses only the selected user’s accounts, plan, cash flows, and transactions')]
    public function test_safe_to_spend_is_scoped_to_selected_user(): void
    {
        $selected = User::factory()->average()->create();
        $other = User::factory()->reckless()->create();

        FinancialPlan::factory()->for($selected)->create([
            'monthly_income_cents' => 100_000,
            'savings_percent' => 0,
            'safety_buffer_cents' => 0,
        ]);
        FinancialPlan::factory()->for($other)->create([
            'monthly_income_cents' => 999_000,
            'savings_percent' => 0,
            'safety_buffer_cents' => 0,
        ]);

        Account::factory()->for($selected)->create(['balance_cents' => 50_000]);
        Account::factory()->for($other)->create(['balance_cents' => 5_000_000]);

        PlannedCashFlow::factory()->for($other)->create([
            'kind' => 'income',
            'amount_cents' => 1_000_000,
            'due_on' => '2026-07-28',
        ]);

        $result = app(SafeToSpendService::class)->forUser($selected, Carbon::parse('2026-07-15'));

        $this->assertSame(50_000, $result['safe_to_spend_cents']);
        $this->assertSame(50_000, $result['breakdown']['available_cash_cents']);
        $this->assertSame(0, $result['breakdown']['remaining_expected_income_cents']);
    }

    #[TestDox('Cash flows, spend, and alerts from other months do not leak into the selected period')]
    public function test_period_isolation_excludes_other_month_cash_flows_spend_and_alerts(): void
    {
        $user = User::factory()->average()->create();

        FinancialPlan::factory()->for($user)->create([
            'monthly_income_cents' => 100_000,
            'savings_percent' => 0,
            'safety_buffer_cents' => 0,
        ]);
        Account::factory()->for($user)->create(['balance_cents' => 10_000]);

        PlannedCashFlow::factory()->for($user)->create([
            'name' => 'June paycheck',
            'kind' => 'income',
            'amount_cents' => 260_000,
            'due_on' => '2026-06-28',
        ]);
        PlannedCashFlow::factory()->for($user)->bill()->create([
            'name' => 'June rent',
            'amount_cents' => 165_000,
            'due_on' => '2026-06-30',
            'is_essential' => true,
        ]);
        PlannedCashFlow::factory()->for($user)->create([
            'name' => 'July paycheck',
            'kind' => 'income',
            'amount_cents' => 50_000,
            'due_on' => '2026-07-28',
        ]);

        Transaction::factory()->for($user)->reviewed()->create([
            'merchant' => 'June Grocery',
            'kind' => TransactionKind::Expense,
            'bucket' => Bucket::Need,
            'amount_cents' => 8_000,
            'transaction_date' => '2026-06-20',
        ]);
        Transaction::factory()->for($user)->reviewed()->create([
            'merchant' => 'July Grocery',
            'kind' => TransactionKind::Expense,
            'bucket' => Bucket::Need,
            'amount_cents' => 3_000,
            'transaction_date' => '2026-07-20',
        ]);
        Transaction::factory()->for($user)->reviewed()->create([
            'merchant' => 'June Big Purchase',
            'kind' => TransactionKind::Expense,
            'bucket' => Bucket::Want,
            'amount_cents' => 150_000,
            'transaction_date' => '2026-06-15',
        ]);

        $midJuly = app(SafeToSpendService::class)->forUser($user, Carbon::parse('2026-07-15'));

        $this->assertSame('2026-07', $midJuly['period']);
        $this->assertSame('2026-07-15', $midJuly['effective_on']);
        $this->assertSame(50_000, $midJuly['breakdown']['remaining_expected_income_cents']);
        $this->assertSame(0, $midJuly['breakdown']['upcoming_essential_bills_cents']);
        $this->assertSame(3_000, $midJuly['bucket_actuals']['need']);
        $this->assertSame(0, $midJuly['bucket_actuals']['want']);
        $this->assertCount(0, $midJuly['unusual_alerts']);
    }

    #[TestDox('Excludes income due before asOf and non-essential bills from the safe-to-spend breakdown')]
    public function test_excludes_past_income_and_non_essential_bills(): void
    {
        $user = User::factory()->average()->create();

        FinancialPlan::factory()->for($user)->create([
            'monthly_income_cents' => 100_000,
            'savings_percent' => 0,
            'safety_buffer_cents' => 0,
        ]);
        Account::factory()->for($user)->create(['balance_cents' => 10_000]);

        PlannedCashFlow::factory()->for($user)->create([
            'name' => 'Already paid paycheck',
            'kind' => 'income',
            'amount_cents' => 260_000,
            'due_on' => '2026-07-10',
        ]);
        PlannedCashFlow::factory()->for($user)->create([
            'name' => 'Upcoming paycheck',
            'kind' => 'income',
            'amount_cents' => 50_000,
            'due_on' => '2026-07-28',
        ]);
        PlannedCashFlow::factory()->for($user)->bill()->create([
            'name' => 'Rent',
            'amount_cents' => 20_000,
            'due_on' => '2026-07-30',
            'is_essential' => true,
        ]);
        PlannedCashFlow::factory()->for($user)->create([
            'name' => 'Streaming',
            'kind' => 'bill',
            'amount_cents' => 1_500,
            'due_on' => '2026-07-29',
            'is_essential' => false,
            'bucket' => Bucket::Want,
        ]);

        $result = app(SafeToSpendService::class)->forUser($user, Carbon::parse('2026-07-15'));

        $this->assertSame(50_000, $result['breakdown']['remaining_expected_income_cents']);
        $this->assertSame(20_000, $result['breakdown']['upcoming_essential_bills_cents']);
        $this->assertSame(40_000, $result['safe_to_spend_cents']);
    }

    #[TestDox('Reviewed income does not inflate bucket spending actuals')]
    public function test_reviewed_income_does_not_inflate_bucket_actuals(): void
    {
        $user = User::factory()->average()->create();

        FinancialPlan::factory()->for($user)->create([
            'monthly_income_cents' => 100_000,
            'savings_percent' => 0,
            'safety_buffer_cents' => 0,
        ]);
        Account::factory()->for($user)->create(['balance_cents' => 0]);

        Transaction::factory()->for($user)->reviewed()->create([
            'kind' => TransactionKind::Income,
            'bucket' => Bucket::Savings,
            'amount_cents' => 250_000,
            'transaction_date' => '2026-07-08',
        ]);

        Transaction::factory()->for($user)->reviewed()->create([
            'kind' => TransactionKind::Expense,
            'bucket' => Bucket::Need,
            'amount_cents' => 3_000,
            'transaction_date' => '2026-07-09',
        ]);

        $result = app(SafeToSpendService::class)->forUser($user, Carbon::parse('2026-07-15'));

        $this->assertSame(3_000, $result['bucket_actuals']['need']);
        $this->assertSame(0, $result['bucket_actuals']['savings']);
    }

    #[TestDox('Overspent savings target clamps remaining savings at zero so STS is not inflated')]
    public function test_remaining_savings_target_clamps_at_zero_when_overspent(): void
    {
        $user = User::factory()->average()->create();

        FinancialPlan::factory()->for($user)->create([
            'monthly_income_cents' => 100_000,
            'savings_percent' => 20,
            'safety_buffer_cents' => 0,
        ]);
        Account::factory()->for($user)->create(['balance_cents' => 50_000]);

        Transaction::factory()->for($user)->reviewed()->create([
            'kind' => TransactionKind::Expense,
            'bucket' => Bucket::Savings,
            'amount_cents' => 30_000,
            'transaction_date' => '2026-07-10',
        ]);

        $result = app(SafeToSpendService::class)->forUser($user, Carbon::parse('2026-07-15'));

        $this->assertSame(20_000, $result['bucket_targets']['savings']);
        $this->assertSame(30_000, $result['bucket_actuals']['savings']);
        $this->assertSame(0, $result['breakdown']['remaining_savings_target_cents']);
        // 50_000 available - 0 remaining savings target (not -10_000)
        $this->assertSame(50_000, $result['safe_to_spend_cents']);
    }

    #[TestDox('Surfaces at most three unusual expense alerts at or above 100000 cents')]
    public function test_unusual_alerts_include_large_expenses_capped_at_three(): void
    {
        $user = User::factory()->average()->create();

        FinancialPlan::factory()->for($user)->create([
            'monthly_income_cents' => 100_000,
            'savings_percent' => 0,
            'safety_buffer_cents' => 0,
        ]);
        Account::factory()->for($user)->create(['balance_cents' => 0]);

        Transaction::factory()->for($user)->create([
            'kind' => TransactionKind::Expense,
            'merchant' => 'Below Threshold',
            'amount_cents' => 99_999,
            'transaction_date' => '2026-07-05',
        ]);
        Transaction::factory()->for($user)->create([
            'kind' => TransactionKind::Expense,
            'merchant' => 'Big Four',
            'amount_cents' => 100_000,
            'transaction_date' => '2026-07-06',
        ]);
        Transaction::factory()->for($user)->create([
            'kind' => TransactionKind::Expense,
            'merchant' => 'Big Three',
            'amount_cents' => 150_000,
            'transaction_date' => '2026-07-07',
        ]);
        Transaction::factory()->for($user)->create([
            'kind' => TransactionKind::Expense,
            'merchant' => 'Big Two',
            'amount_cents' => 200_000,
            'transaction_date' => '2026-07-08',
        ]);
        Transaction::factory()->for($user)->create([
            'kind' => TransactionKind::Expense,
            'merchant' => 'Big One',
            'amount_cents' => 300_000,
            'transaction_date' => '2026-07-09',
        ]);

        $result = app(SafeToSpendService::class)->forUser($user, Carbon::parse('2026-07-15'));

        $this->assertCount(3, $result['unusual_alerts']);
        $this->assertSame('Big One', $result['unusual_alerts'][0]['merchant']);
        $this->assertSame('3000.00', $result['unusual_alerts'][0]['amount']);
        $this->assertSame('Big Two', $result['unusual_alerts'][1]['merchant']);
        $this->assertSame('Big Three', $result['unusual_alerts'][2]['merchant']);
        $merchants = array_column($result['unusual_alerts'], 'merchant');
        $this->assertNotContains('Below Threshold', $merchants);
        $this->assertNotContains('Big Four', $merchants);
    }
}
