<?php

namespace App\Modules\Catalog;

use App\Modules\SmartSearch\MatchingStrategyResolver;
use App\Modules\SmartSearch\MeiliClientFactory;
use App\Modules\SmartSearch\MeiliFilter;
use App\Modules\SmartSearch\SearchIndexWriteRegistry;
use App\Support\SafeLog;

final class CatalogMeiliHandleLookup implements CatalogTextIdResolver
{
    public const LIMIT = 1000;

    public function __construct(private readonly mixed $client = null)
    {
    }

    /**
     * شناسه‌های متنی از Meili. null یعنی باید LIKE فعلی بماند.
     *
     * @return list<string>|null
     */
    public function handles(string $normalized, ?string $groupSlug = null): ?array
    {
        if (mb_strlen($normalized) < 2) {
            return null;
        }
        if (! in_array((string) config('smart-search.driver'), ['meilisearch', 'failover'], true)) {
            return null;
        }

        try {
            $client = $this->client ?? MeiliClientFactory::make();
            $uid = SearchIndexWriteRegistry::active()['products'];
            $raw = $client->index($uid)->rawSearch($normalized, self::searchParams($normalized, $groupSlug));
            $hits = is_array($raw['hits'] ?? null) ? $raw['hits'] : [];

            return self::handlesFromHits($hits);
        } catch (\Throwable $e) {
            SafeLog::warning('catalog_meili_lookup_failed', [
                'q' => mb_substr($normalized, 0, 100),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function searchParams(string $normalized, ?string $groupSlug = null): array
    {
        return array_filter([
            'limit' => self::LIMIT,
            'matchingStrategy' => MatchingStrategyResolver::forQuery($normalized),
            'filter' => MeiliFilter::equals('group_slug', (string) $groupSlug),
            'attributesToRetrieve' => ['handle', 'id'],
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  list<mixed>  $hits
     * @return list<string>
     */
    public static function handlesFromHits(array $hits): array
    {
        $handles = [];
        foreach ($hits as $hit) {
            if (! is_array($hit)) {
                continue;
            }
            $handle = (string) ($hit['handle'] ?? $hit['id'] ?? '');
            if ($handle === '' || isset($handles[$handle])) {
                continue;
            }
            $handles[$handle] = $handle;
        }

        return array_values($handles);
    }
}
