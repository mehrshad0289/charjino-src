<?php

namespace App\Modules\SmartSearch;

use App\Modules\SmartSearch\Contracts\SmartSearchEngine;
use App\Modules\SmartSearch\Data\SmartSearchCandidates;
use App\Modules\SmartSearch\Data\SmartSearchRequest;
use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery as MeiliSearchQuery;

final class MeilisearchSmartSearchEngine implements SmartSearchEngine
{
    private const OTHER_EXCLUDED_GROUPS = ['game-topup', 'gift-card', 'game-keys', 'steam-gifts'];

    private ?Client $client = null;

    private function client(): Client
    {
        return $this->client ??= MeiliClientFactory::make();
    }

    public function search(SmartSearchRequest $request): SmartSearchCandidates
    {
        $q = $request->normalized;
        if (mb_strlen($q) < 2) {
            return SmartSearchProductHydrator::payload('empty', $q, [], 1, 1, false);
        }
        if ($request->productsOnly()) {
            return $this->products($request);
        }

        return $this->suggest($request);
    }

    private function suggest(SmartSearchRequest $request): SmartSearchCandidates
    {
        $uids = SearchIndexWriteRegistry::active();
        $strategy = MatchingStrategyResolver::forQuery($request->normalized);
        $queries = [
            $this->query($uids['taxonomies'], $request->normalized, 3, $strategy),
            $this->query($uids['brands'], $request->normalized, 2, $strategy),
            $this->query($uids['products'], $request->normalized, 3, $strategy, MeiliFilter::equals('group_slug', 'game-topup')),
            $this->query($uids['products'], $request->normalized, 3, $strategy, MeiliFilter::equals('group_slug', 'gift-card')),
            $this->query($uids['products'], $request->normalized, 3, $strategy, MeiliFilter::equals('group_slug', 'game-keys')),
            $this->query($uids['products'], $request->normalized, 4, $strategy, MeiliFilter::notIn('group_slug', self::OTHER_EXCLUDED_GROUPS)),
        ];
        if (preg_match('/steam|استیم/u', $request->normalized) === 1) {
            $queries[] = $this->query($uids['products'], $request->normalized, 2, $strategy, MeiliFilter::equals('group_slug', 'steam-gifts'));
        }
        $raw = $this->client()->multiSearch($queries);
        $results = is_array($raw['results'] ?? null) ? $raw['results'] : [];
        $taxHits = $this->hits($results[0] ?? []);
        $brandHits = $this->hits($results[1] ?? []);
        $productSlices = [];
        for ($i = 2, $n = count($results); $i < $n; $i++) {
            $productSlices[] = $this->hits($results[$i] ?? []);
        }
        $references = [
            ...SmartSearchProductHydrator::references($taxHits),
            ...SmartSearchProductHydrator::references($brandHits),
        ];
        $mergedHits = $this->roundRobin($productSlices, 12);
        $products = SmartSearchProductHydrator::products($mergedHits, $request->normalized, false);
        $products = array_slice($products, 0, 5);
        if ($references === [] && $products === []) {
            return SmartSearchProductHydrator::payload('empty', $request->normalized, [], 1, 1, false);
        }
        if ($references === []) {
            return $this->products($request);
        }
        $items = array_slice([...$references, ...$products], 0, 10);

        return SmartSearchProductHydrator::payload('suggest', $request->normalized, $items, 1, 1, false);
    }

    private function products(SmartSearchRequest $request): SmartSearchCandidates
    {
        $uid = SearchIndexWriteRegistry::active()['products'];
        $strategy = MatchingStrategyResolver::forQuery($request->normalized);
        $filter = MeiliFilter::and(array_filter([
            MeiliFilter::equals('group_slug', (string) $request->group),
            MeiliFilter::equals('platform', (string) $request->platform),
            MeiliFilter::equals('regions', (string) $request->region),
            ($request->group === null && preg_match('/steam|استیم/u', $request->normalized) !== 1)
                ? MeiliFilter::equals('is_steam', '0')
                : null,
        ]));
        $wantedRegions = RegionParser::parse($request->normalized);
        $limit = min(30, max(1, $request->perPage));
        $offset = ($request->page - 1) * $limit;
        $overFetch = $wantedRegions !== [] ? min(80, $limit + 70) : ($limit + 8);
        $params = array_filter([
            'limit' => $overFetch,
            'offset' => $offset,
            'matchingStrategy' => $strategy,
            'filter' => $filter,
            'attributesToRetrieve' => ['id', 'handle', 'packages_labels'],
        ], fn ($v) => $v !== null);
        $raw = $this->client()->index($uid)->rawSearch($request->normalized, $params);
        $hits = is_array($raw['hits'] ?? null) ? $raw['hits'] : [];
        $estimated = (int) ($raw['estimatedTotalHits'] ?? $raw['totalHits'] ?? count($hits));
        $items = SmartSearchProductHydrator::products($hits, $request->normalized, true);
        $items = array_slice($items, 0, $limit);
        if ($items === []) {
            return SmartSearchProductHydrator::payload('empty', $request->normalized, [], $request->page, 1, false);
        }
        $lastPage = max(1, (int) ceil(max($estimated, 1) / $limit));

        return SmartSearchProductHydrator::payload(
            'products',
            $request->normalized,
            $items,
            $request->page,
            $lastPage,
            $request->page < $lastPage,
        );
    }

    private function query(
        string $uid,
        string $q,
        int $limit,
        string $strategy,
        ?string $filter = null,
        int $offset = 0,
    ): MeiliSearchQuery {
        $query = (new MeiliSearchQuery())
            ->setIndexUid($uid)
            ->setQuery($q)
            ->setLimit($limit)
            ->setMatchingStrategy($strategy)
            ->setAttributesToRetrieve(['id', 'handle', 'packages_labels', 'kind', 'slug', 'name', 'name_en', 'group_slug', 'active_products_count']);
        if ($filter !== null) {
            $query->setFilter([$filter]);
        }
        if ($offset > 0) {
            $query->setOffset($offset);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return list<array<string, mixed>>
     */
    private function hits(array $result): array
    {
        $hits = $result['hits'] ?? [];

        return is_array($hits) ? $hits : [];
    }

    /**
     * @param  list<list<array<string, mixed>>>  $slices
     * @return list<array<string, mixed>>
     */
    private function roundRobin(array $slices, int $cap): array
    {
        $out = [];
        $seen = [];
        $max = 0;
        foreach ($slices as $slice) {
            $max = max($max, count($slice));
        }
        for ($i = 0; $i < $max; $i++) {
            foreach ($slices as $slice) {
                $hit = $slice[$i] ?? null;
                if (! is_array($hit)) {
                    continue;
                }
                $id = (string) ($hit['handle'] ?? $hit['id'] ?? '');
                if ($id === '' || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $out[] = $hit;
                if (count($out) >= $cap) {
                    return $out;
                }
            }
        }

        return $out;
    }
}
