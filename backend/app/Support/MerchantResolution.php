<?php

namespace App\Support;

use App\Enums\MatchStrategy;
use App\Models\Merchant;
use App\Models\MerchantAlias;

final class MerchantResolution
{
    public function __construct(
        public readonly Merchant $merchant,
        public readonly MerchantAlias $alias,
        public readonly MatchStrategy $strategy,
        public readonly string $rawDescriptor,
        public readonly string $normalizedDescriptor,
        public readonly string $explanation,
    ) {}
}
