<?php

namespace App\Support;

use App\Enums\Bucket;
use App\Enums\ReviewSource;

final readonly class CategorizationResult
{
    public function __construct(
        public ?Bucket $bucket,
        public ?string $subcategory,
        public int $confidence,
        public ReviewSource $source,
        public string $explanation,
        public bool $autoReview,
        public ?int $ruleId = null,
    ) {}

    public function isConfident(int $threshold = 85): bool
    {
        return $this->bucket !== null && $this->confidence >= $threshold && $this->autoReview;
    }
}
