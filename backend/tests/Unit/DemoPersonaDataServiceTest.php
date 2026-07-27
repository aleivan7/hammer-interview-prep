<?php

namespace Tests\Unit;

use App\Enums\ReviewSource;
use App\Models\Account;
use App\Models\CategorizationRule;
use App\Models\PlannedCashFlow;
use App\Models\ReviewAudit;
use App\Models\Transaction;
use App\Services\DemoPersonaDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Demo persona reset edges not covered by broader seed-shape suites.
 */
class DemoPersonaDataServiceTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Reset restores one persona and clears review audits without touching others')]
    public function test_reset_restores_persona_and_clears_audits_without_touching_others(): void
    {
        $service = app(DemoPersonaDataService::class);
        [$reckless, $average, $hnw] = $service->seedAllPersonas(Carbon::parse('2026-07-25'));

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

        $service->resetFinancialData($average, Carbon::parse('2026-07-25'));

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
