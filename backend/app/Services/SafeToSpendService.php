<?php

namespace App\Services;

use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\FinancialPlan;
use App\Models\PlannedCashFlow;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class SafeToSpendService
{
    /**
     * @return array{
     *   safe_to_spend_cents: int,
     *   amount: string,
     *   effective_on: string,
     *   period: string,
     *   breakdown: array<string, int|string>,
     *   bucket_actuals: array{need: int, want: int, savings: int},
     *   bucket_targets: array{need: int, want: int, savings: int},
     *   unusual_alerts: list<array{merchant: string, amount: string, message: string}>
     * }
     */
    public function forPeriod(?CarbonInterface $asOf = null): array
    {
        $asOf = Carbon::parse($asOf?->toDateString() ?? 'now')->startOfDay();
        $periodStart = $asOf->copy()->startOfMonth();
        $periodEnd = $asOf->copy()->endOfMonth();

        $plan = FinancialPlan::query()->first() ?? new FinancialPlan([
            'needs_percent' => 50,
            'wants_percent' => 30,
            'savings_percent' => 20,
            'safety_buffer_cents' => 0,
            'monthly_income_cents' => 0,
        ]);

        $availableCashCents = (int) Account::query()->sum('balance_cents');

        $remainingIncomeCents = (int) PlannedCashFlow::query()
            ->where('kind', 'income')
            ->whereBetween('due_on', [$asOf->toDateString(), $periodEnd->toDateString()])
            ->sum('amount_cents');

        $upcomingEssentialBillsCents = (int) PlannedCashFlow::query()
            ->where('kind', 'bill')
            ->where('is_essential', true)
            ->whereBetween('due_on', [$asOf->toDateString(), $periodEnd->toDateString()])
            ->sum('amount_cents');

        $spentByBucket = $this->spentByBucket($periodStart, $periodEnd);
        $incomeBasis = max($plan->monthly_income_cents, 1);
        $savingsTargetCents = intdiv($incomeBasis * $plan->savings_percent, 100);
        $remainingSavingsTargetCents = max(0, $savingsTargetCents - $spentByBucket['savings']);

        $safeToSpendCents = Money::sumCents(
            $availableCashCents,
            $remainingIncomeCents,
            -$upcomingEssentialBillsCents,
            -$remainingSavingsTargetCents,
            -$plan->safety_buffer_cents,
        );

        $needsTarget = intdiv($incomeBasis * $plan->needs_percent, 100);
        $wantsTarget = intdiv($incomeBasis * $plan->wants_percent, 100);

        return [
            'safe_to_spend_cents' => $safeToSpendCents,
            'amount' => Money::centsToDollarString($safeToSpendCents),
            'effective_on' => $asOf->toDateString(),
            'period' => $periodStart->format('Y-m'),
            'breakdown' => [
                'available_cash_cents' => $availableCashCents,
                'available_cash' => Money::centsToDollarString($availableCashCents),
                'remaining_expected_income_cents' => $remainingIncomeCents,
                'remaining_expected_income' => Money::centsToDollarString($remainingIncomeCents),
                'upcoming_essential_bills_cents' => $upcomingEssentialBillsCents,
                'upcoming_essential_bills' => Money::centsToDollarString($upcomingEssentialBillsCents),
                'remaining_savings_target_cents' => $remainingSavingsTargetCents,
                'remaining_savings_target' => Money::centsToDollarString($remainingSavingsTargetCents),
                'safety_buffer_cents' => $plan->safety_buffer_cents,
                'safety_buffer' => Money::centsToDollarString($plan->safety_buffer_cents),
            ],
            'bucket_actuals' => $spentByBucket,
            'bucket_targets' => [
                'need' => $needsTarget,
                'want' => $wantsTarget,
                'savings' => $savingsTargetCents,
            ],
            'unusual_alerts' => $this->unusualAlerts($periodStart, $periodEnd),
        ];
    }

    /**
     * @return array{need: int, want: int, savings: int}
     */
    private function spentByBucket(CarbonInterface $start, CarbonInterface $end): array
    {
        $totals = [
            'need' => 0,
            'want' => 0,
            'savings' => 0,
        ];

        $transactions = Transaction::query()
            ->whereNotNull('reviewed_at')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->get(['kind', 'bucket', 'amount_cents']);

        foreach ($transactions as $transaction) {
            if ($transaction->kind === TransactionKind::Transfer || $transaction->bucket === null) {
                continue;
            }

            $signed = match ($transaction->kind) {
                TransactionKind::Expense => $transaction->amount_cents,
                TransactionKind::Refund => -$transaction->amount_cents,
                TransactionKind::Income => 0,
                TransactionKind::Transfer => 0,
            };

            $key = $transaction->bucket->value;
            $totals[$key] = ($totals[$key] ?? 0) + $signed;
        }

        return $totals;
    }

    /**
     * @return list<array{merchant: string, amount: string, message: string}>
     */
    private function unusualAlerts(CarbonInterface $start, CarbonInterface $end): array
    {
        $large = Transaction::query()
            ->where('kind', TransactionKind::Expense)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->where('amount_cents', '>=', 100000)
            ->orderByDesc('amount_cents')
            ->limit(3)
            ->get();

        return $large->map(fn (Transaction $transaction) => [
            'merchant' => $transaction->merchant,
            'amount' => Money::centsToDollarString($transaction->amount_cents),
            'message' => "Unusual spending detected: {$transaction->merchant} for ".Money::centsToDollarString($transaction->amount_cents).'.',
        ])->all();
    }
}
