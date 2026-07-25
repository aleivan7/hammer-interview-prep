<?php

namespace Tests\Feature;

use App\Enums\Bucket;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\FinancialPlan;
use App\Models\PlannedCashFlow;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Dashboard API: safe-to-spend, plan, accounts, cash flows, and recent activity.
 */
class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Returns the safe-to-spend envelope with plan, accounts, and recent transactions')]
    public function test_dashboard_returns_safe_to_spend_envelope(): void
    {
        FinancialPlan::factory()->create([
            'needs_percent' => 50,
            'wants_percent' => 30,
            'savings_percent' => 20,
            'safety_buffer_cents' => 25_000,
            'monthly_income_cents' => 520_000,
        ]);

        Account::factory()->create([
            'name' => 'Everyday Checking',
            'balance_cents' => 184_350,
            'sort_order' => 1,
        ]);

        PlannedCashFlow::factory()->create([
            'name' => 'Paycheck',
            'kind' => 'income',
            'amount_cents' => 260_000,
            'due_on' => '2026-07-28',
        ]);

        Transaction::factory()->reviewed()->create([
            'merchant' => 'HEB',
            'kind' => TransactionKind::Expense,
            'bucket' => Bucket::Need,
            'amount_cents' => 8_423,
            'transaction_date' => '2026-07-20',
        ]);

        Transaction::factory()->unreviewed()->create([
            'merchant' => 'Mystery Shop',
            'transaction_date' => '2026-07-21',
        ]);

        $response = $this->getJson('/api/dashboard?period=2026-07');

        $response->assertOk()
            ->assertJsonPath('data.persona.name', 'Jordan Lee')
            ->assertJsonPath('data.plan.needs_percent', 50)
            ->assertJsonPath('data.unreviewed_count', 1)
            ->assertJsonPath('data.accounts.0.name', 'Everyday Checking')
            ->assertJsonStructure([
                'data' => [
                    'safe_to_spend' => [
                        'safe_to_spend_cents',
                        'amount',
                        'breakdown',
                        'bucket_actuals',
                        'bucket_targets',
                        'unusual_alerts',
                    ],
                    'cash_flows',
                    'recent_transactions',
                ],
            ]);

        $this->assertIsInt($response->json('data.safe_to_spend.safe_to_spend_cents'));
        $this->assertSame(8423, $response->json('data.safe_to_spend.bucket_actuals.need'));
        $this->assertContains('HEB', collect($response->json('data.recent_transactions'))->pluck('merchant')->all());
    }
}
