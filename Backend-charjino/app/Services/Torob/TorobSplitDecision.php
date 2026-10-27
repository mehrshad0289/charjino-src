<?php

namespace App\Services\Torob;

use App\Models\Sku;

final class TorobSplitDecision
{
    public const REASON_EXCLUDED = 'excluded';

    public const REASON_NOT_ENABLED = 'not_enabled';

    public const REASON_SINGLE_SKU = 'single_sku';

    public const REASON_NO_ORDERABLE_SKU = 'no_orderable_sku';

    public const REASON_OVER_MAX = 'over_max';

    public const REASON_EMPTY_LABELS = 'empty_labels';

    public const REASON_AMBIGUOUS_TITLES = 'ambiguous_titles';

    public const REASON_UNKNOWN_MAPPING = 'unknown_mapping';

    /**
     * @param  list<Sku>  $skus
     */
    public function __construct(
        public bool $split,
        public ?string $reason,
        public array $skus,
        public ?Sku $canonical,
    ) {}

    public static function skip(?string $reason, ?Sku $canonical = null): self
    {
        return new self(false, $reason, [], $canonical);
    }

    /**
     * @param  list<Sku>  $skus
     */
    public static function yes(array $skus, Sku $canonical): self
    {
        return new self(true, null, $skus, $canonical);
    }
}
