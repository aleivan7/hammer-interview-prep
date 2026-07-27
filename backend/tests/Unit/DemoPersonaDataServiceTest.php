<?php

namespace Tests\Unit;

use App\Enums\PersonaType;
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
 * Demo persona seeding: idempotency, ownership isolation, and rule-account integrity.
 */
class DemoPersonaDataServiceTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Seeding all personas twice keeps one user per persona and does not duplicate financial rows')]
    public function test_seed_all_personas_is_idempotent(): void
    {
        $service = app(DemoPersonaDataService::class);
        $asOf = Carbon::parse('2026-07-25');

        $first = $service->seedAllPersonas($asOf);
        $firstSnapshot = $this->ownershipSnapshot();

        $second = $service->seedAllPersonas($asOf);

        $this->assertCount(3, $first);
        $this->assertCount(3, $second);
        $this->assertSame(
            collect($first)->pluck('id')->sort()->values()->all(),
            collect($second)->pluck('id')->sort()->values()->all(),
        );
        $this->assertSame(3, User::query()->whereNotNull('persona_type')->count());
        $this->assertSame($firstSnapshot, $this->ownershipSnapshot());
    }

    #[TestDox('Seeded financial rows belong only to their persona and rules reference that persona’s accounts')]
    public function test_seeded_rows_belong_to_persona_and_rules_use_owned_accounts(): void
    {
        $users = app(DemoPersonaDataService::class)->seedAllPersonas(Carbon::parse('2026-07-25'));
        $userIds = collect($users)->pluck('id')->all();

        foreach ($users as $user) {
            $this->assertNotNull($user->persona_type);
            $this->assertSame(1, FinancialPlan::query()->where('user_id', $user->id)->count());
            $this->assertGreaterThan(0, Account::query()->where('user_id', $user->id)->count());
            $this->assertGreaterThan(0, PlannedCashFlow::query()->where('user_id', $user->id)->count());
            $this->assertGreaterThan(0, Transaction::query()->where('user_id', $user->id)->count());
            $this->assertGreaterThan(0, CategorizationRule::query()->where('user_id', $user->id)->count());

            $ownedAccountIds = Account::query()
                ->where('user_id', $user->id)
                ->pluck('id')
                ->all();

            $foreignAccountLinks = CategorizationRule::query()
                ->where('user_id', $user->id)
                ->whereNotNull('account_id')
                ->whereNotIn('account_id', $ownedAccountIds)
                ->count();

            $this->assertSame(0, $foreignAccountLinks);
        }

        foreach ([
            Account::class,
            CategorizationRule::class,
            FinancialPlan::class,
            PlannedCashFlow::class,
            Transaction::class,
        ] as $model) {
            $this->assertSame(
                0,
                $model::query()->whereNotIn('user_id', $userIds)->count(),
            );
        }
    }

    #[TestDox('Re-seeding one persona restores its data without changing other personas')]
    public function test_seed_persona_restores_only_that_persona(): void
    {
        $service = app(DemoPersonaDataService::class);
        $asOf = Carbon::parse('2026-07-25');
        [$reckless, $average, $hnw] = $service->seedAllPersonas($asOf);

        $averageOriginal = $this->userSnapshot($average->id);
        $hnwBefore = $this->userSnapshot($hnw->id);
        $recklessBefore = $this->userSnapshot($reckless->id);

        Transaction::factory()->for($average)->create([
            'merchant' => 'Drift Purchase',
            'amount_cents' => 1234,
        ]);
        CategorizationRule::factory()->for($average)->create([
            'name' => 'Drift Rule',
            'merchant_contains' => 'drift',
        ]);
        Account::query()->where('user_id', $average->id)->update(['balance_cents' => 1]);

        $drifted = $this->userSnapshot($average->id);
        $this->assertSame(
            $averageOriginal['counts']['transactions'] + 1,
            $drifted['counts']['transactions'],
        );
        $this->assertSame(
            $averageOriginal['counts']['rules'] + 1,
            $drifted['counts']['rules'],
        );
        $this->assertContains('Drift Purchase', $drifted['merchants']);
        $this->assertSame([1], Account::query()->where('user_id', $average->id)->pluck('balance_cents')->unique()->all());

        $restored = $service->seedPersona(PersonaType::Average, $asOf);

        $this->assertSame($average->id, $restored->id);
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $average->id,
            'merchant' => 'Drift Purchase',
        ]);
        $this->assertDatabaseMissing('categorization_rules', [
            'user_id' => $average->id,
            'name' => 'Drift Rule',
        ]);
        $this->assertSame($averageOriginal, $this->userSnapshot($average->id));
        $this->assertNotSame([1], Account::query()->where('user_id', $average->id)->pluck('balance_cents')->unique()->all());
        $this->assertSame($hnwBefore, $this->userSnapshot($hnw->id));
        $this->assertSame($recklessBefore, $this->userSnapshot($reckless->id));
    }

    /**
     * @return array{
     *   users: list<int>,
     *   accounts: int,
     *   rules: int,
     *   plans: int,
     *   cash_flows: int,
     *   transactions: int,
     *   ownership: array<string, array<string, int>>
     * }
     */
    private function ownershipSnapshot(): array
    {
        $users = User::query()
            ->whereNotNull('persona_type')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $ownership = [];

        foreach ($users as $userId) {
            $ownership[(string) $userId] = $this->userSnapshot($userId)['counts'];
        }

        return [
            'users' => $users,
            'accounts' => Account::query()->count(),
            'rules' => CategorizationRule::query()->count(),
            'plans' => FinancialPlan::query()->count(),
            'cash_flows' => PlannedCashFlow::query()->count(),
            'transactions' => Transaction::query()->count(),
            'ownership' => $ownership,
        ];
    }

    /**
     * @return array{
     *   counts: array<string, int>,
     *   account_names: list<string>,
     *   rule_names: list<string>,
     *   plan: array<string, mixed>|null,
     *   cash_flow_names: list<string>,
     *   merchants: list<string>
     * }
     */
    private function userSnapshot(int $userId): array
    {
        return [
            'counts' => [
                'accounts' => Account::query()->where('user_id', $userId)->count(),
                'rules' => CategorizationRule::query()->where('user_id', $userId)->count(),
                'plans' => FinancialPlan::query()->where('user_id', $userId)->count(),
                'cash_flows' => PlannedCashFlow::query()->where('user_id', $userId)->count(),
                'transactions' => Transaction::query()->where('user_id', $userId)->count(),
            ],
            'account_names' => Account::query()
                ->where('user_id', $userId)
                ->orderBy('name')
                ->pluck('name')
                ->all(),
            'rule_names' => CategorizationRule::query()
                ->where('user_id', $userId)
                ->orderBy('name')
                ->pluck('name')
                ->all(),
            'plan' => FinancialPlan::query()->where('user_id', $userId)->first()?->only([
                'monthly_income_cents',
                'needs_percent',
                'wants_percent',
                'savings_percent',
                'safety_buffer_cents',
            ]),
            'cash_flow_names' => PlannedCashFlow::query()
                ->where('user_id', $userId)
                ->orderBy('name')
                ->pluck('name')
                ->all(),
            'merchants' => Transaction::query()
                ->where('user_id', $userId)
                ->orderBy('merchant')
                ->pluck('merchant')
                ->all(),
        ];
    }
}
