<?php

namespace Tests\Unit;

use App\Enums\PersonaType;
use App\Enums\ReviewSource;
use App\Models\Account;
use App\Models\CategorizationRule;
use App\Models\FinancialPlan;
use App\Models\PlannedCashFlow;
use App\Models\ReviewAudit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DemoPersonaDataService;
use App\Services\SafeToSpendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Demo persona seeding: ownership, idempotency, day clamp, and STS shape.
 */
class DemoPersonaDataServiceTest extends TestCase
{
    use RefreshDatabase;

    private DemoPersonaDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DemoPersonaDataService::class);
    }

    #[TestDox('Seeding all personas twice keeps stable user IDs and does not duplicate rows')]
    public function test_seed_all_personas_is_idempotent(): void
    {
        $asOf = Carbon::parse('2026-07-25');
        $first = $this->service->seedAllPersonas($asOf);
        $firstIds = collect($first)->pluck('id')->all();
        $firstEmails = collect($first)->pluck('email')->all();

        $second = $this->service->seedAllPersonas($asOf);

        $this->assertSame($firstIds, collect($second)->pluck('id')->all());
        $this->assertSame($firstEmails, collect($second)->pluck('email')->all());
        $this->assertSame(3, User::query()->count());
        $this->assertSame(11, Account::query()->count());
        $this->assertSame(10, PlannedCashFlow::query()->count());
        $this->assertSame(7, CategorizationRule::query()->count());
        $this->assertSame(200, Transaction::query()->count());
        $this->assertSame(3, FinancialPlan::query()->count());
    }

    #[TestDox('Seeded financial rows are owned by the persona user and account FKs stay in-persona')]
    public function test_seeded_rows_belong_only_to_owning_persona(): void
    {
        $users = $this->service->seedAllPersonas(Carbon::parse('2026-07-25'));

        foreach ($users as $user) {
            $accountIds = Account::query()->where('user_id', $user->id)->pluck('id');
            $foreignAccountIds = Account::query()->where('user_id', '!=', $user->id)->pluck('id');

            $this->assertTrue(
                FinancialPlan::query()->where('user_id', $user->id)->exists(),
                "Missing financial plan for {$user->email}",
            );
            $this->assertSame(
                0,
                CategorizationRule::query()
                    ->where('user_id', $user->id)
                    ->whereNotNull('account_id')
                    ->whereNotIn('account_id', $accountIds)
                    ->count(),
            );
            $this->assertSame(
                Transaction::query()->where('user_id', $user->id)->count(),
                Transaction::query()
                    ->where('user_id', $user->id)
                    ->whereIn('account_id', $accountIds)
                    ->count(),
            );
            $this->assertSame(
                0,
                Transaction::query()
                    ->where('user_id', $user->id)
                    ->whereIn('account_id', $foreignAccountIds)
                    ->count(),
            );
        }
    }

    #[TestDox('Each persona seeds the expected account, cash-flow, rule, and transaction counts')]
    public function test_seeded_personas_have_exact_dataset_sizes_and_review_stamps(): void
    {
        $users = collect($this->service->seedAllPersonas(Carbon::parse('2026-07-25')))
            ->keyBy(fn (User $user) => $user->persona_type->value);

        $expected = [
            PersonaType::Reckless->value => ['accounts' => 3, 'cash_flows' => 4, 'rules' => 1, 'transactions' => 66, 'reviewed' => 26, 'unreviewed' => 40],
            PersonaType::Average->value => ['accounts' => 3, 'cash_flows' => 3, 'rules' => 2, 'transactions' => 67, 'reviewed' => 27, 'unreviewed' => 40],
            PersonaType::HighNetWorth->value => ['accounts' => 5, 'cash_flows' => 3, 'rules' => 4, 'transactions' => 67, 'reviewed' => 27, 'unreviewed' => 40],
        ];

        foreach ($expected as $persona => $counts) {
            $user = $users[$persona];
            $this->assertSame($counts['accounts'], Account::query()->where('user_id', $user->id)->count(), $persona.' accounts');
            $this->assertSame($counts['cash_flows'], PlannedCashFlow::query()->where('user_id', $user->id)->count(), $persona.' cash flows');
            $this->assertSame($counts['rules'], CategorizationRule::query()->where('user_id', $user->id)->count(), $persona.' rules');
            $this->assertSame($counts['transactions'], Transaction::query()->where('user_id', $user->id)->count(), $persona.' transactions');
            $this->assertSame($counts['reviewed'], Transaction::query()->where('user_id', $user->id)->reviewed()->count(), $persona.' reviewed');
            $this->assertSame($counts['unreviewed'], Transaction::query()->where('user_id', $user->id)->unreviewed()->count(), $persona.' unreviewed');
        }

        $reviewed = Transaction::query()->reviewed()->get();
        $this->assertTrue($reviewed->every(fn (Transaction $txn) => $txn->bucket !== null
            && $txn->reviewed_at !== null
            && $txn->review_source === ReviewSource::Manual
            && $txn->confidence === 100));

        $unreviewed = Transaction::query()->unreviewed()->get();
        $this->assertTrue($unreviewed->every(fn (Transaction $txn) => $txn->bucket === null
            && $txn->subcategory === null
            && $txn->reviewed_at === null));

        $this->assertTrue(
            CategorizationRule::query()
                ->where('merchant_contains', 'united way')
                ->where('auto_review', false)
                ->exists(),
        );
    }

    #[TestDox('asOf day clamping maps due_day 31 onto the last day of short months')]
    public function test_as_of_clamps_due_day_onto_last_day_of_february(): void
    {
        $user = $this->service->seedPersona(PersonaType::Average, Carbon::parse('2026-02-15'));

        $dueDates = PlannedCashFlow::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->pluck('due_on')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $this->assertContains('2026-02-28', $dueDates);
        $this->assertTrue(
            PlannedCashFlow::query()
                ->where('user_id', $user->id)
                ->where('name', 'Acme Corp paycheck')
                ->whereDate('due_on', '2026-02-28')
                ->exists(),
        );
        $this->assertSame(
            0,
            PlannedCashFlow::query()
                ->where('user_id', $user->id)
                ->whereMonth('due_on', '!=', 2)
                ->count(),
        );
        $this->assertSame(
            0,
            Transaction::query()
                ->where('user_id', $user->id)
                ->where(function ($query) {
                    $query->where('transaction_date', '<', '2026-02-01')
                        ->orWhere('transaction_date', '>', '2026-02-28');
                })
                ->count(),
        );
    }

    #[TestDox('Seeded personas produce stable Safe-to-Spend golden cents at the demo asOf')]
    public function test_seeded_personas_produce_golden_safe_to_spend_cents(): void
    {
        $users = collect($this->service->seedAllPersonas(Carbon::parse('2026-07-25')))
            ->keyBy(fn (User $user) => $user->persona_type->value);
        $safeToSpend = app(SafeToSpendService::class);
        $asOf = Carbon::parse('2026-07-25');

        $this->assertSame(
            -483_750,
            $safeToSpend->forUser($users[PersonaType::Reckless->value], $asOf)['safe_to_spend_cents'],
        );
        $this->assertSame(
            135_550,
            $safeToSpend->forUser($users[PersonaType::Average->value], $asOf)['safe_to_spend_cents'],
        );
        $this->assertSame(
            55_129_800,
            $safeToSpend->forUser($users[PersonaType::HighNetWorth->value], $asOf)['safe_to_spend_cents'],
        );
    }

    #[TestDox('Reset restores one persona and clears review audits without touching others')]
    public function test_reset_restores_persona_and_clears_audits_without_touching_others(): void
    {
        [$reckless, $average, $hnw] = $this->service->seedAllPersonas(Carbon::parse('2026-07-25'));

        $otherBefore = [
            'accounts' => Account::query()->where('user_id', $hnw->id)->orderBy('id')->get()->toArray(),
            'transactions' => Transaction::query()->where('user_id', $hnw->id)->orderBy('id')->get()->toArray(),
            'rules' => CategorizationRule::query()->where('user_id', $hnw->id)->orderBy('id')->get()->toArray(),
            'cash_flows' => PlannedCashFlow::query()->where('user_id', $hnw->id)->orderBy('id')->get()->toArray(),
            'reckless_tx_count' => Transaction::query()->where('user_id', $reckless->id)->count(),
        ];

        $txn = Transaction::query()->where('user_id', $average->id)->unreviewed()->firstOrFail();
        ReviewAudit::query()->create([
            'transaction_id' => $txn->id,
            'action' => 'review',
            'source' => ReviewSource::Manual,
            'bucket' => 'want',
            'subcategory' => 'dining',
            'confidence' => 100,
            'explanation' => 'Manual mutation for reset coverage.',
            'previous_state' => ['bucket' => null, 'subcategory' => null],
        ]);
        $this->assertSame(1, ReviewAudit::query()->where('transaction_id', $txn->id)->count());

        Transaction::factory()->for($average)->create([
            'merchant' => 'Mutation Extra',
            'amount_cents' => 123,
        ]);
        CategorizationRule::factory()->for($average)->create([
            'name' => 'Mutation rule',
            'merchant_contains' => 'mutation',
        ]);

        $this->service->resetFinancialData($average, Carbon::parse('2026-07-25'));

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $average->id,
            'merchant' => 'Mutation Extra',
        ]);
        $this->assertDatabaseMissing('categorization_rules', [
            'user_id' => $average->id,
            'name' => 'Mutation rule',
        ]);
        $this->assertSame(67, Transaction::query()->where('user_id', $average->id)->count());
        $this->assertSame(2, CategorizationRule::query()->where('user_id', $average->id)->count());
        $this->assertSame(0, ReviewAudit::query()->count());

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
            $otherBefore['cash_flows'],
            PlannedCashFlow::query()->where('user_id', $hnw->id)->orderBy('id')->get()->toArray(),
        );
        $this->assertSame(
            $otherBefore['reckless_tx_count'],
            Transaction::query()->where('user_id', $reckless->id)->count(),
        );
    }
}
