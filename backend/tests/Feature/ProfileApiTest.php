<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CategorizationRule;
use App\Models\FinancialPlan;
use App\Models\PlannedCashFlow;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DemoPersonaDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Profile and per-persona reset endpoints.
 */
class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Profile endpoint returns the selected demo user summary')]
    public function test_profile_returns_selected_user_summary(): void
    {
        $user = User::factory()->average()->create();
        FinancialPlan::factory()->for($user)->create([
            'monthly_income_cents' => 520_000,
            'needs_percent' => 50,
            'wants_percent' => 30,
            'savings_percent' => 20,
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Everyday Checking',
            'balance_cents' => 184_350,
        ]);

        $this->getJsonAs($user, '/api/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Jordan Lee')
            ->assertJsonPath('data.email', 'jordan.lee@clearspend.demo')
            ->assertJsonPath('data.monthly_income_cents', 520_000)
            ->assertJsonPath('data.account_count', 1)
            ->assertJsonPath('data.accounts.0.name', 'Everyday Checking')
            ->assertJsonPath('data.plan.needs_percent', 50);
    }

    #[TestDox('Reset restores only the selected user’s financial data')]
    public function test_reset_restores_only_selected_user(): void
    {
        $service = app(DemoPersonaDataService::class);
        [$reckless, $average, $hnw] = $service->seedAllPersonas(Carbon::parse('2026-07-25'));

        $otherBefore = [
            'accounts' => Account::query()->where('user_id', $hnw->id)->orderBy('id')->get()->toArray(),
            'transactions' => Transaction::query()->where('user_id', $hnw->id)->orderBy('id')->get()->toArray(),
            'rules' => CategorizationRule::query()->where('user_id', $hnw->id)->orderBy('id')->get()->toArray(),
            'plan' => FinancialPlan::query()->where('user_id', $hnw->id)->first()?->toArray(),
            'cash_flows' => PlannedCashFlow::query()->where('user_id', $hnw->id)->orderBy('id')->get()->toArray(),
        ];

        Transaction::factory()->for($average)->create([
            'merchant' => 'Edited Extra Purchase',
            'amount_cents' => 999,
        ]);
        CategorizationRule::factory()->for($average)->create([
            'name' => 'Temporary rule',
            'merchant_contains' => 'temporary',
        ]);
        Account::query()->where('user_id', $average->id)->first()?->update(['balance_cents' => 1]);

        $response = $this->postJsonAs($average, '/api/profile/reset');

        $response->assertOk()
            ->assertJsonPath('data.id', $average->id)
            ->assertJsonPath('message', 'Demo profile data restored to its original seeded state.');

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $average->id,
            'merchant' => 'Edited Extra Purchase',
        ]);
        $this->assertDatabaseMissing('categorization_rules', [
            'user_id' => $average->id,
            'name' => 'Temporary rule',
        ]);
        $this->assertSame(
            184_350,
            Account::query()->where('user_id', $average->id)->where('name', 'Everyday Checking')->value('balance_cents'),
        );
        $this->assertGreaterThan(30, Transaction::query()->where('user_id', $average->id)->count());
        $this->assertSame(2, CategorizationRule::query()->where('user_id', $average->id)->count());
        $this->assertGreaterThan(
            1,
            Transaction::query()
                ->where('user_id', $average->id)
                ->selectRaw('transaction_date, COUNT(*) as c')
                ->groupBy('transaction_date')
                ->having('c', '>', 1)
                ->count(),
        );

        $this->assertSame(
            $otherBefore['accounts'],
            Account::query()->where('user_id', $hnw->id)->orderBy('id')->get()->toArray(),
        );
        $this->assertSame(
            $otherBefore['transactions'],
            Transaction::query()->where('user_id', $hnw->id)->orderBy('id')->get()->toArray(),
        );
        $this->assertSame(
            $otherBefore['rules'],
            CategorizationRule::query()->where('user_id', $hnw->id)->orderBy('id')->get()->toArray(),
        );
        $this->assertSame(
            $otherBefore['plan'],
            FinancialPlan::query()->where('user_id', $hnw->id)->first()?->toArray(),
        );
        $this->assertSame(
            $otherBefore['cash_flows'],
            PlannedCashFlow::query()->where('user_id', $hnw->id)->orderBy('id')->get()->toArray(),
        );

        $this->assertSame(3, Account::query()->where('user_id', $reckless->id)->count());
    }
}
