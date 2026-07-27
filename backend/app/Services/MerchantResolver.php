<?php

namespace App\Services;

use App\Enums\MatchStrategy;
use App\Models\MerchantAlias;
use App\Support\CatalogNormalizer;
use App\Support\MerchantResolution;
use Illuminate\Support\Collection;

final class MerchantResolver
{
    /**
     * Higher values win before priority/id tie-breakers.
     */
    private const STRATEGY_RANK = [
        MatchStrategy::Exact->value => 400,
        MatchStrategy::Prefix->value => 300,
        MatchStrategy::WholeToken->value => 200,
        MatchStrategy::SafeContains->value => 100,
    ];

    public function resolve(string $rawDescriptor): ?MerchantResolution
    {
        $normalized = CatalogNormalizer::descriptor($rawDescriptor);

        if ($normalized === '') {
            return null;
        }

        /** @var Collection<int, MerchantAlias> $aliases */
        $aliases = MerchantAlias::query()
            ->enabled()
            ->with('merchant')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $matches = $aliases
            ->filter(fn (MerchantAlias $alias): bool => $this->matches($normalized, $alias))
            ->sort(function (MerchantAlias $left, MerchantAlias $right): int {
                $rankCompare = (self::STRATEGY_RANK[$right->match_strategy->value] ?? 0)
                    <=> (self::STRATEGY_RANK[$left->match_strategy->value] ?? 0);

                if ($rankCompare !== 0) {
                    return $rankCompare;
                }

                $priorityCompare = $left->priority <=> $right->priority;

                if ($priorityCompare !== 0) {
                    return $priorityCompare;
                }

                return $left->id <=> $right->id;
            })
            ->values();

        /** @var MerchantAlias|null $winner */
        $winner = $matches->first();

        if ($winner === null || $winner->merchant === null) {
            return null;
        }

        return new MerchantResolution(
            merchant: $winner->merchant,
            alias: $winner,
            strategy: $winner->match_strategy,
            rawDescriptor: $rawDescriptor,
            normalizedDescriptor: $normalized,
            explanation: sprintf(
                'Matched %s via %s alias "%s" (priority %d).',
                $winner->merchant->name,
                $winner->match_strategy->value,
                $winner->pattern,
                $winner->priority,
            ),
        );
    }

    private function matches(string $normalizedDescriptor, MerchantAlias $alias): bool
    {
        $pattern = $alias->normalized_pattern;

        if ($pattern === '') {
            return false;
        }

        return match ($alias->match_strategy) {
            MatchStrategy::Exact => $normalizedDescriptor === $pattern,
            MatchStrategy::Prefix => $this->matchesPrefix($normalizedDescriptor, $pattern),
            MatchStrategy::WholeToken => $this->matchesWholeToken($normalizedDescriptor, $pattern),
            MatchStrategy::SafeContains => $this->matchesSafeContains($normalizedDescriptor, $pattern),
        };
    }

    private function matchesPrefix(string $normalizedDescriptor, string $pattern): bool
    {
        if (! str_starts_with($normalizedDescriptor, $pattern)) {
            return false;
        }

        if ($normalizedDescriptor === $pattern) {
            return true;
        }

        return $normalizedDescriptor[strlen($pattern)] === ' ';
    }

    private function matchesWholeToken(string $normalizedDescriptor, string $pattern): bool
    {
        $descriptorTokens = explode(' ', $normalizedDescriptor);
        $patternTokens = explode(' ', $pattern);
        $patternCount = count($patternTokens);

        if ($patternCount === 0) {
            return false;
        }

        $limit = count($descriptorTokens) - $patternCount;

        for ($index = 0; $index <= $limit; $index++) {
            $slice = array_slice($descriptorTokens, $index, $patternCount);

            if ($slice === $patternTokens) {
                return true;
            }
        }

        return false;
    }

    private function matchesSafeContains(string $normalizedDescriptor, string $pattern): bool
    {
        // Safe contains still requires token boundaries so short aliases like
        // SHELL cannot match SHELLPOINT without an explicit dedicated alias.
        return $this->matchesWholeToken($normalizedDescriptor, $pattern);
    }
}
