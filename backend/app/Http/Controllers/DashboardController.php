<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\FinancialPlan;
use App\Models\PlannedCashFlow;
use App\Models\Transaction;
use App\Services\Money;
use App\Services\SafeToSpendService;
use App\Support\DemoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SafeToSpendService $safeToSpend,
        private readonly DemoUserContext $demoUser,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $this->demoUser->user();
        $period = $request->string('period')->toString();
        $asOf = $period !== ''
            ? Carbon::createFromFormat('Y-m', $period)->endOfMonth()
            : Carbon::now();

        $forecast = $this->safeToSpend->forUser($user, $asOf);
        $plan = FinancialPlan::query()->forUser($user)->first();

        $cashFlows = PlannedCashFlow::query()
            ->forUser($user)
            ->whereBetween('due_on', [$asOf->copy()->startOfMonth()->toDateString(), $asOf->copy()->endOfMonth()->toDateString()])
            ->orderBy('due_on')
            ->get()
            ->map(fn (PlannedCashFlow $flow) => [
                'id' => $flow->id,
                'name' => $flow->name,
                'amount_cents' => $flow->amount_cents,
                'amount' => Money::centsToDollarString($flow->amount_cents),
                'kind' => $flow->kind,
                'due_on' => $flow->due_on->format('Y-m-d'),
                'is_essential' => $flow->is_essential,
                'bucket' => $flow->bucket?->value,
            ]);

        $recent = Transaction::query()
            ->forUser($user)
            ->with('account')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $unreviewedCount = Transaction::query()->forUser($user)->unreviewed()->count();

        return response()->json([
            'data' => [
                'persona' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'persona_type' => $user->persona_type?->value,
                    'persona_label' => $user->persona_label ?? $user->persona_type?->label(),
                    'description' => $user->description,
                    'member_since' => $user->member_since?->format('Y-m-d'),
                    'avatar_initials' => $user->avatar_initials,
                ],
                'safe_to_spend' => $forecast,
                'plan' => $plan ? [
                    'needs_percent' => $plan->needs_percent,
                    'wants_percent' => $plan->wants_percent,
                    'savings_percent' => $plan->savings_percent,
                    'safety_buffer_cents' => $plan->safety_buffer_cents,
                    'safety_buffer' => Money::centsToDollarString($plan->safety_buffer_cents),
                    'monthly_income_cents' => $plan->monthly_income_cents,
                    'monthly_income' => Money::centsToDollarString($plan->monthly_income_cents),
                ] : null,
                'cash_flows' => $cashFlows,
                'accounts' => AccountResource::collection(
                    Account::query()->forUser($user)->orderBy('sort_order')->orderBy('id')->get()
                )->resolve(),
                'recent_transactions' => TransactionResource::collection($recent)->resolve(),
                'unreviewed_count' => $unreviewedCount,
            ],
        ]);
    }
}
