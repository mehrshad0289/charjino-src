<?php

namespace App\Models\Concerns;

use Laravel\Scout\Searchable;

/**
 * Scout is wired but indexing stays off until SMART_SEARCH_SCOUT_SYNC is enabled.
 * SKU prices and product images must never appear in toSearchableArray().
 */
trait SearchIndexable
{
    use Searchable;

    public function shouldBeSearchable(): bool
    {
        return (bool) config('charjino.smart_search.scout_sync')
            && config('scout.driver') === 'meilisearch';
    }

    public function searchableAs(): string
    {
        return (string) config('scout.prefix').$this->searchIndexName();
    }

    abstract protected function searchIndexName(): string;

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->getKey(),
        ];
    }
}
