<?php

namespace Tests\Feature;

use App\Enums\Bucket;
use App\Enums\PersonaType;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\FinancialPlan;
use App\Models\PlannedCashFlow;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DemoPersonaDataService;
use App\Services\SafeToSpendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $user = $this->withDemoUser(User::factory()->average()->create());

        FinancialPlan::factory()->for($user)->create([
            'needs_percent' => 50,
            'wants_percent' => 30,
            'savings_percent' => 20,
            'safety_buffer_cents' => 25_000,
            'monthly_income_cents' => 520_000,
        ]);

        Account::factory()->for($user)->create([
            'name' => 'Everyday Checking',
            'balance_cents' => 184_350,
            'sort_order' => 1,
        ]);

        PlannedCashFlow::factory()->for($user)->create([
            'name' => 'Paycheck',
            'kind' => 'income',
            'amount_cents' => 260_000,
            'due_on' => '2026-07-28',
        ]);

        Transaction::factory()->for($user)->reviewed()->create([
            'merchant' => 'HEB',
            'kind' => TransactionKind::Expense,
            'bucket' => Bucket::Need,
            'amount_cents' => 8_423,
            'transaction_date' => '2026-07-20',
        ]);

        Transaction::factory()->for($user)->unreviewed()->create([
            'merchant' => 'Mystery Shop',
            'transaction_date' => '2026-07-21',
        ]);

        $other = User::factory()->reckless()->create();
        Transaction::factory()->for($other)->unreviewed()->create([
            'merchant' => 'Other User Shop',
        ]);

        $response = $this->getJson('/api/dashboard?period=2026-07');

        $response->assertOk()
            ->assertJsonPath('data.persona.name', 'Jordan Lee')
            ->assertJsonPath('data.persona.id', $user->id)
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
        $this->assertNotContains('Other User Shop', collect($response->json('data.recent_transactions'))->pluck('merchant')->all());
    }

    #[TestDox('Dashboard data belongs only to the selected demo user')]
    public function test_dashboard_is_scoped_to_selected_user(): void
    {
        $selected = User::factory()->average()->create();
        $other = User::factory()->reckless()->create();

        FinancialPlan::factory()->for($selected)->create(['monthly_income_cents' => 520_000]);
        FinancialPlan::factory()->for($other)->create(['monthly_income_cents' => 450_000]);
        Account::factory()->for($selected)->create(['name' => 'Jordan Checking', 'balance_cents' => 100_000]);
        Account::factory()->for($other)->create(['name' => 'Alex Checking', 'balance_cents' => 10_000]);

        $this->getJsonAs($selected, '/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.persona.name', 'Jordan Lee')
            ->assertJsonPath('data.accounts.0.name', 'Jordan Checking')
            ->assertJsonPath('data.plan.monthly_income_cents', 520_000);
    }

    #[TestDox('Each seeded persona produces meaningfully different dashboard results')]
    public function test_seeded_personas_produce_different_dashboard_results(): void
    {
        $users = app(DemoPersonaDataService::class)->seedAllPersonas(Carbon::parse('2026-07-25'));
        $safeToSpend = app(SafeToSpendService::class);

        $values = collect($users)->mapWithKeys(function (User $user) use ($safeToSpend) {
            $forecast = $safeToSpend->forUser($user, Carbon::parse('2026-07-25'));

            return [$user->persona_type->value => [
                'safe_to_spend_cents' => $forecast['safe_to_spend_cents'],
                'income' => $user->financialPlan?->monthly_income_cents,
                'accounts' => $user->accounts()->count(),
                'unreviewed' => $user->transactions()->unreviewed()->count(),
            ]];
        });

        $this->assertSame(3, $values->count());
        $this->assertLessThan(0, $values[PersonaType::Reckless->value]['safe_to_spend_cents']);
        $this->assertGreaterThan(0, $values[PersonaType::Average->value]['safe_to_spend_cents']);
        $this->assertGreaterThan(
            $values[PersonaType::Reckless->value]['safe_to_spend_cents'],
            $values[PersonaType::Average->value]['safe_to_spend_cents'],
        );
        $this->assertGreaterThan(
            $values[PersonaType::Average->value]['safe_to_spend_cents'],
            $values[PersonaType::HighNetWorth->value]['safe_to_spend_cents'],
        );
        $this->assertNotSame(
            $values[PersonaType::Reckless->value]['income'],
            $values[PersonaType::HighNetWorth->value]['income'],
        );
        $this->assertSame(5, $values[PersonaType::HighNetWorth->value]['accounts']);
        $this->assertGreaterThan(0, $values[PersonaType::Reckless->value]['unreviewed']);
    }
}
