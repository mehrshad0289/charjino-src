<?php

namespace App\Modules\SmartSearch;

use App\Exceptions\ApiException;
use App\Modules\SmartSearch\Contracts\SmartSearchEngine;
use App\Modules\SmartSearch\Data\SmartSearchCandidates;
use App\Modules\SmartSearch\Data\SmartSearchRequest;

final class SmartSearchService
{
    public function search(
        string $raw,
        int $page = 1,
        int $perPage = 10,
        string $mode = 'auto',
        ?string $group = null,
        ?string $platform = null,
        ?string $region = null,
    ): array {
        $raw = mb_substr($raw, 0, 100);
        $normalized = PersianTextNormalizer::normalize($raw);
        $page = max(1, $page);
        $perPage = max(1, min(30, $perPage));
        $mode = in_array($mode, ['auto', 'products', 'ads'], true) ? $mode : 'auto';
        $group = MeiliFilter::isSafeSlug($group) ? $group : null;
        $platform = MeiliFilter::isSafeSlug($platform) ? $platform : null;
        $region = MeiliFilter::isSafeSlug($region) ? $region : null;
        if (mb_strlen($normalized) < 2) {
            return SmartSearchProductHydrator::payload('empty', $normalized, [], $page, 1, false)->toArray();
        }
        $request = new SmartSearchRequest(
            $raw,
            $normalized,
            $page,
            $perPage,
            $mode,
            $group,
            $platform,
            $region,
        );
        try {
            $result = $this->engine()->search($request);
            $mapped = KeyboardLayoutTranscoder::toPersian($raw);
            $preferMapped = KeyboardLayoutTranscoder::shouldPreferMapped($raw);
            $retryEmpty = $result->isEmpty() && KeyboardLayoutTranscoder::shouldRetry($normalized);
            if (($preferMapped || $retryEmpty) && $mapped !== '' && $mapped !== $normalized && mb_strlen($mapped) >= 2) {
                $retry = new SmartSearchRequest($mapped, $mapped, $page, $perPage, $mode, $group, $platform, $region, true);
                $retried = $this->engine()->search($retry);
                if ($preferMapped || ! $retried->isEmpty()) {
                    $result = new SmartSearchCandidates(
                        $retried->mode,
                        $normalized,
                        $retried->items,
                        $retried->pagination,
                    );
                }
            }
        } catch (\Throwable $e) {
            if ((string) config('smart-search.driver') === 'failover') {
                $result = app(DatabaseSmartSearchEngine::class)->search($request);
            } elseif ((string) config('smart-search.driver') === 'meilisearch') {
                throw new ApiException('جستجو فعلاً در دسترس نیست.', 503, 'SEARCH_UNAVAILABLE');
            } else {
                throw $e;
            }
        }

        return $this->sanitize($result)->toArray();
    }

    private function engine(): SmartSearchEngine
    {
        return match ((string) config('smart-search.driver')) {
            'meilisearch' => app(MeilisearchSmartSearchEngine::class),
            'failover' => app(FailoverSmartSearchEngine::class),
            default => app(DatabaseSmartSearchEngine::class),
        };
    }

    private function sanitize(SmartSearchCandidates $result): SmartSearchCandidates
    {
        $items = [];
        $suggest = in_array($result->mode, ['suggest', 'entities'], true);
        foreach ($result->items as $item) {
            if (! is_array($item)) {
                continue;
            }
            unset($item['hashid']);
            if ($suggest) {
                unset($item['minPrice']);
            }
            $items[] = $item;
        }

        return new SmartSearchCandidates($result->mode, $result->query, $items, $result->pagination);
    }
}
