<?php

namespace App\Modules\SmartSearch\Data;

final class SmartSearchRequest
{
    public function __construct(
        public readonly string $raw,
        public readonly string $normalized,
        public readonly int $page,
        public readonly int $perPage,
        public readonly string $mode,
        public readonly ?string $group,
        public readonly ?string $platform,
        public readonly ?string $region,
        public readonly bool $keyboardFallback = false,
    ) {}

    public function hasExplicitFilter(): bool
    {
        return $this->group !== null || $this->platform !== null || $this->region !== null;
    }

    public function productsOnly(): bool
    {
        return in_array($this->mode, ['products', 'ads'], true) || $this->hasExplicitFilter();
    }
}
