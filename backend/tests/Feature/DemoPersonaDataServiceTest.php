<?php

namespace Tests\Feature;

use App\Enums\PersonaType;
use App\Enums\ReviewSource;
use App\Models\Account;
use App\Models\CategorizationRule;
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
 * Demo persona seeding: ownership, idempotency, day clamping, and golden STS.
 */
class DemoPersonaDataServiceTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Seeding all personas twice keeps the same users and does not duplicate rows')]
    public function test_seed_all_personas_is_idempotent(): void
    {
        $service = app(DemoPersonaDataService::class);
        $asOf = Carbon::parse('2026-07-25');

        $first = $service->seedAllPersonas($asOf);
        $firstIds = collect($first)->pluck('id')->all();
        $firstEmails = collect($first)->pluck('email')->sort()->values()->all();
        $firstCounts = $this->financialRowCounts();

        $second = $service->seedAllPersonas($asOf);
        $secondIds = collect($second)->pluck('id')->all();
        $secondEmails = collect($second)->pluck('email')->sort()->values()->all();

        $this->assertSame($firstIds, $secondIds);
        $this->assertSame($firstEmails, $secondEmails);
        $this->assertSame([
            'alex.rivera@clearspend.demo',
            'jordan.lee@clearspend.demo',
            'morgan.chen@clearspend.demo',
        ], $secondEmails);
        $this->assertSame(3, User::query()->count());
        $this->assertSame($firstCounts, $this->financialRowCounts());
    }

    #[TestDox('Each persona seeds the expected account, cash-flow, rule, and transaction counts')]
    public function test_seeded_personas_have_expected_shapes(): void
    {
        [$reckless, $average, $hnw] = app(DemoPersonaDataService::class)
            ->seedAllPersonas(Carbon::parse('2026-07-25'));

        $this->assertPersonaShape($reckless, accounts: 3, cashFlows: 4, rules: 1, reviewed: 26, unreviewed: 40);
        $this->assertPersonaShape($average, accounts: 3, cashFlows: 3, rules: 2, reviewed: 27, unreviewed: 40);
        $this->assertPersonaShape($hnw, accounts: 5, cashFlows: 3, rules: 4, reviewed: 27, unreviewed: 40);
    }

    #[TestDox('Every seeded financial row is owned by its persona and accounts stay user-scoped')]
    public function test_seeded_financial_rows_belong_only_to_their_persona(): void
    {
        $users = app(DemoPersonaDataService::class)->seedAllPersonas(Carbon::parse('2026-07-25'));
        $userIds = collect($users)->pluck('id');

        foreach ($users as $user) {
            $this->assertSame(1, FinancialPlan::query()->where('user_id', $user->id)->count());

            $foreignAccountLinks = Transaction::query()
                ->where('transactions.user_id', $user->id)
                ->whereNotNull('account_id')
                ->whereDoesntHave('account', fn ($query) => $query->where('user_id', $user->id))
                ->count();
            $this->assertSame(0, $foreignAccountLinks);

            $foreignRuleAccounts = CategorizationRule::query()
                ->where('user_id', $user->id)
                ->whereNotNull('account_id')
                ->whereDoesntHave('account', fn ($query) => $query->where('user_id', $user->id))
                ->count();
            $this->assertSame(0, $foreignRuleAccounts);
        }

        $this->assertSame(0, Account::query()->whereNotIn('user_id', $userIds)->count());
        $this->assertSame(0, Transaction::query()->whereNotIn('user_id', $userIds)->count());
        $this->assertSame(0, CategorizationRule::query()->whereNotIn('user_id', $userIds)->count());
        $this->assertSame(0, PlannedCashFlow::query()->whereNotIn('user_id', $userIds)->count());
        $this->assertSame(0, FinancialPlan::query()->whereNotIn('user_id', $userIds)->count());
        $this->assertSame($userIds->count(), FinancialPlan::query()->distinct()->count('user_id'));
    }

    #[TestDox('Reviewed seed rows are stamped manual; unreviewed rows stay bucketless')]
    public function test_reviewed_and_unreviewed_seed_invariants(): void
    {
        [$average] = app(DemoPersonaDataService::class)->seedAllPersonas(Carbon::parse('2026-07-25'));

        $reviewed = Transaction::query()->where('user_id', $average->id)->whereNotNull('reviewed_at')->get();
        $unreviewed = Transaction::query()->where('user_id', $average->id)->whereNull('reviewed_at')->get();

        $this->assertNotEmpty($reviewed);
        $this->assertNotEmpty($unreviewed);

        foreach ($reviewed as $transaction) {
            $this->assertSame(ReviewSource::Manual, $transaction->review_source);
            $this->assertNotNull($transaction->bucket);
            $this->assertNotNull($transaction->reviewed_at);
        }

        foreach ($unreviewed as $transaction) {
            $this->assertNull($transaction->bucket);
            $this->assertNull($transaction->reviewed_at);
            $this->assertNull($transaction->review_source);
        }
    }

    #[TestDox('Seed asOf clamps due_day and transaction day past the month length')]
    public function test_seed_clamps_days_to_month_length(): void
    {
        $average = app(DemoPersonaDataService::class)
            ->seedPersona(PersonaType::Average, Carbon::parse('2026-02-15'));

        $paycheckDue = PlannedCashFlow::query()
            ->where('user_id', $average->id)
            ->where('name', 'Acme Corp paycheck')
            ->value('due_on');

        $this->assertSame('2026-02-28', optional($paycheckDue)->format('Y-m-d') ?? (string) $paycheckDue);

        $dates = Transaction::query()
            ->where('user_id', $average->id)
            ->pluck('transaction_date')
            ->map(fn ($date) => $date instanceof Carbon ? $date->format('Y-m-d') : (string) $date);

        $this->assertTrue($dates->every(fn (string $date) => str_starts_with($date, '2026-02-')));
        $this->assertFalse($dates->contains(fn (string $date) => (int) substr($date, -2) > 28));
    }

    #[TestDox('Golden safe-to-spend values stay stable for the demo asOf date')]
    public function test_seeded_personas_have_golden_safe_to_spend_values(): void
    {
        $users = app(DemoPersonaDataService::class)->seedAllPersonas(Carbon::parse('2026-07-25'));
        $safeToSpend = app(SafeToSpendService::class);
        $asOf = Carbon::parse('2026-07-25');

        $values = collect($users)->mapWithKeys(
            fn (User $user) => [$user->persona_type->value => $safeToSpend->forUser($user, $asOf)['safe_to_spend_cents']],
        );

        $this->assertSame(-483_750, $values[PersonaType::Reckless->value]);
        $this->assertSame(135_550, $values[PersonaType::Average->value]);
        $this->assertSame(55_129_800, $values[PersonaType::HighNetWorth->value]);
    }

    #[TestDox('Reset without persona_type falls back to the Average dataset')]
    public function test_reset_without_persona_type_falls_back_to_average(): void
    {
        $user = User::factory()->create([
            'persona_type' => null,
            'email' => 'fallback@clearspend.demo',
            'name' => 'Fallback User',
        ]);

        app(DemoPersonaDataService::class)->resetFinancialData($user, Carbon::parse('2026-07-25'));

        $this->assertSame(3, Account::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, CategorizationRule::query()->where('user_id', $user->id)->count());
        $this->assertSame(27, Transaction::query()->where('user_id', $user->id)->whereNotNull('reviewed_at')->count());
        $this->assertSame(40, Transaction::query()->where('user_id', $user->id)->whereNull('reviewed_at')->count());
        $this->assertSame(
            520_000,
            FinancialPlan::query()->where('user_id', $user->id)->value('monthly_income_cents'),
        );
        $this->assertTrue(
            CategorizationRule::query()
                ->where('user_id', $user->id)
                ->where('merchant_contains', 'netflix')
                ->exists(),
        );
    }

    /**
     * @return array{users: int, accounts: int, cash_flows: int, rules: int, transactions: int, plans: int}
     */
    private function financialRowCounts(): array
    {
        return [
            'users' => User::query()->count(),
            'accounts' => Account::query()->count(),
            'cash_flows' => PlannedCashFlow::query()->count(),
            'rules' => CategorizationRule::query()->count(),
            'transactions' => Transaction::query()->count(),
            'plans' => FinancialPlan::query()->count(),
        ];
    }

    private function assertPersonaShape(
        User $user,
        int $accounts,
        int $cashFlows,
        int $rules,
        int $reviewed,
        int $unreviewed,
    ): void {
        $this->assertSame($accounts, Account::query()->where('user_id', $user->id)->count());
        $this->assertSame($cashFlows, PlannedCashFlow::query()->where('user_id', $user->id)->count());
        $this->assertSame($rules, CategorizationRule::query()->where('user_id', $user->id)->count());
        $this->assertSame(
            $reviewed,
            Transaction::query()->where('user_id', $user->id)->whereNotNull('reviewed_at')->count(),
        );
        $this->assertSame(
            $unreviewed,
            Transaction::query()->where('user_id', $user->id)->whereNull('reviewed_at')->count(),
        );
        $this->assertSame(1, FinancialPlan::query()->where('user_id', $user->id)->count());
    }
}
