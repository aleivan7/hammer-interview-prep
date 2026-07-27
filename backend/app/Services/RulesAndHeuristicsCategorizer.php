<?php

namespace App\Services;

use App\Contracts\TransactionCategorizer;
use App\Enums\Bucket;
use App\Enums\ReviewSource;
use App\Enums\TransactionKind;
use App\Models\CategorizationRule;
use App\Models\Transaction;
use App\Support\CategorizationResult;

final class RulesAndHeuristicsCategorizer implements TransactionCategorizer
{
    public function __construct(
        private readonly MerchantResolver $merchantResolver,
    ) {}

    public function categorize(Transaction $transaction): CategorizationResult
    {
        $ruleMatch = $this->matchRule($transaction);

        if ($ruleMatch !== null) {
            return $ruleMatch;
        }

        return $this->matchHeuristic($transaction);
    }

    private function matchRule(Transaction $transaction): ?CategorizationResult
    {
        $resolvedMerchantId = $this->resolvedMerchantId($transaction);

        $rules = CategorizationRule::query()
            ->where('user_id', $transaction->user_id)
            ->where('enabled', true)
            ->with(['category', 'merchant'])
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if (! $this->merchantMatches($rule, $transaction, $resolvedMerchantId)) {
                continue;
            }

            if ($rule->account_id !== null && $rule->account_id !== $transaction->account_id) {
                continue;
            }

            if ($rule->amount_cents_min !== null && $transaction->amount_cents < $rule->amount_cents_min) {
                continue;
            }

            if ($rule->amount_cents_max !== null && $transaction->amount_cents > $rule->amount_cents_max) {
                continue;
            }

            $bucket = $rule->category?->bucket ?? $rule->target_bucket;
            $subcategory = $rule->category?->normalized_name ?? $rule->target_subcategory;
            $merchantLabel = $rule->merchant?->name ?? $rule->merchant_contains;

            return new CategorizationResult(
                bucket: $bucket,
                subcategory: $subcategory,
                confidence: 95,
                source: ReviewSource::Rule,
                explanation: $rule->merchant_id !== null
                    ? "Matched rule \"{$rule->name}\" for merchant \"{$merchantLabel}\"."
                    : "Matched rule \"{$rule->name}\" (merchant contains \"{$rule->merchant_contains}\").",
                autoReview: $rule->auto_review,
                ruleId: $rule->id,
                categoryId: $rule->category_id,
            );
        }

        return null;
    }

    private function resolvedMerchantId(Transaction $transaction): ?int
    {
        if ($transaction->merchant_id !== null) {
            return (int) $transaction->merchant_id;
        }

        $descriptor = $transaction->raw_merchant_descriptor ?: $transaction->merchant;

        if (! is_string($descriptor) || trim($descriptor) === '') {
            return null;
        }

        return $this->merchantResolver->resolve($descriptor)?->merchant->id;
    }

    private function merchantMatches(
        CategorizationRule $rule,
        Transaction $transaction,
        ?int $resolvedMerchantId,
    ): bool {
        if ($rule->merchant_id !== null) {
            return $resolvedMerchantId !== null
                && (int) $resolvedMerchantId === (int) $rule->merchant_id;
        }

        if ($rule->merchant_contains === null || $rule->merchant_contains === '') {
            return false;
        }

        return str_contains(
            mb_strtolower((string) $transaction->merchant),
            mb_strtolower($rule->merchant_contains),
        );
    }

    private function matchHeuristic(Transaction $transaction): CategorizationResult
    {
        $merchant = mb_strtolower($transaction->merchant);

        if ($transaction->kind === TransactionKind::Transfer) {
            return new CategorizationResult(
                bucket: Bucket::Savings,
                subcategory: 'transfer',
                confidence: 90,
                source: ReviewSource::Heuristic,
                explanation: 'Transfers default to Savings and are excluded from spending totals.',
                autoReview: true,
            );
        }

        if ($transaction->kind === TransactionKind::Income) {
            return new CategorizationResult(
                bucket: Bucket::Savings,
                subcategory: 'income',
                confidence: 88,
                source: ReviewSource::Heuristic,
                explanation: 'Income is treated as a Savings/plan inflow for review.',
                autoReview: true,
            );
        }

        $heuristics = [
            ['need', 'groceries', 86, ['heb', 'kroger', 'whole foods', 'trader joe', 'walmart grocery']],
            ['need', 'utilities', 84, ['electric', 'utility', 'water bill', 'gas company', 'internet']],
            ['need', 'transportation', 82, ['shell', 'chevron', 'exxon', 'uber', 'lyft', 'metro']],
            ['want', 'dining', 80, ['chipotle', 'starbucks', 'mcdonald', 'restaurant', 'cafe', 'coffee']],
            ['want', 'entertainment', 80, ['netflix', 'spotify', 'hulu', 'disney+', 'steam']],
            ['want', 'shopping', 78, ['target', 'amazon', 'best buy', 'apple store']],
            ['savings', 'debt', 88, ['capital one payment', 'student loan', 'loan payment', 'credit card payment']],
            ['savings', 'savings', 85, ['savings', 'emergency fund', 'brokerage', '401k']],
        ];

        foreach ($heuristics as [$bucket, $subcategory, $confidence, $needles]) {
            foreach ($needles as $needle) {
                if (str_contains($merchant, $needle)) {
                    return new CategorizationResult(
                        bucket: Bucket::from($bucket),
                        subcategory: $subcategory,
                        confidence: $confidence,
                        source: ReviewSource::Heuristic,
                        explanation: "Heuristic match for merchant containing \"{$needle}\".",
                        autoReview: $confidence >= 85,
                    );
                }
            }
        }

        return new CategorizationResult(
            bucket: null,
            subcategory: null,
            confidence: 0,
            source: ReviewSource::Heuristic,
            explanation: 'No confident rule or heuristic match. Manual review required.',
            autoReview: false,
        );
    }
}
