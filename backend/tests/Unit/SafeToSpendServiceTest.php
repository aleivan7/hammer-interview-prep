<?php

namespace Tests\Unit;

use App\Enums\Bucket;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\FinancialPlan;
use App\Models\PlannedCashFlow;
use App\Models\Transaction;
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
        FinancialPlan::factory()->create([
            'needs_percent' => 50,
            'wants_percent' => 30,
            'savings_percent' => 20,
            'safety_buffer_cents' => 25_000,
            'monthly_income_cents' => 520_000,
        ]);

        Account::factory()->create(['balance_cents' => 100_000]);
        Account::factory()->create(['balance_cents' => 50_050]);

        PlannedCashFlow::factory()->create([
            'name' => 'Paycheck',
            'kind' => 'income',
            'amount_cents' => 260_000,
            'due_on' => '2026-07-28',
        ]);

        PlannedCashFlow::factory()->bill()->create([
            'name' => 'Rent',
            'amount_cents' => 165_000,
            'due_on' => '2026-07-30',
        ]);

        $asOf = Carbon::parse('2026-07-25');
        $result = app(SafeToSpendService::class)->forPeriod($asOf);

        // 100000 + 50050 + 260000 - 165000 - remaining savings (104000) - 25000
        // savings target = 20% of 520000 = 104000; no savings spent yet
        $this->assertSame(116_050, $result['safe_to_spend_cents']);
        $this->assertSame('1160.50', $result['amount']);
        $this->assertSame(150_050, $result['breakdown']['available_cash_cents']);
        $this->assertSame(104_000, $result['bucket_targets']['savings']);
    }

    #[TestDox('Excludes transfer transactions from bucket spending actuals')]
    public function test_transfers_are_excluded_from_bucket_spending(): void
    {
        FinancialPlan::factory()->create([
            'monthly_income_cents' => 100_000,
            'savings_percent' => 20,
            'safety_buffer_cents' => 0,
        ]);
        Account::factory()->create(['balance_cents' => 0]);

        Transaction::factory()->reviewed()->create([
            'kind' => TransactionKind::Transfer,
            'bucket' => Bucket::Savings,
            'amount_cents' => 50_000,
            'transaction_date' => '2026-07-10',
        ]);

        Transaction::factory()->reviewed()->create([
            'kind' => TransactionKind::Expense,
            'bucket' => Bucket::Need,
            'amount_cents' => 12_34,
            'transaction_date' => '2026-07-11',
        ]);

        $result = app(SafeToSpendService::class)->forPeriod(Carbon::parse('2026-07-15'));

        $this->assertSame(12_34, $result['bucket_actuals']['need']);
        $this->assertSame(0, $result['bucket_actuals']['savings']);
    }

    #[TestDox('Subtracts refunds from the matching bucket’s spending actuals')]
    public function test_refunds_reduce_bucket_actuals(): void
    {
        FinancialPlan::factory()->create([
            'monthly_income_cents' => 100_000,
            'savings_percent' => 0,
            'safety_buffer_cents' => 0,
        ]);
        Account::factory()->create(['balance_cents' => 0]);

        Transaction::factory()->reviewed()->create([
            'kind' => TransactionKind::Expense,
            'bucket' => Bucket::Want,
            'amount_cents' => 10_000,
            'transaction_date' => '2026-07-05',
        ]);

        Transaction::factory()->reviewed()->create([
            'kind' => TransactionKind::Refund,
            'bucket' => Bucket::Want,
            'amount_cents' => 2_500,
            'transaction_date' => '2026-07-06',
        ]);

        $result = app(SafeToSpendService::class)->forPeriod(Carbon::parse('2026-07-15'));

        $this->assertSame(7_500, $result['bucket_actuals']['want']);
    }
}
