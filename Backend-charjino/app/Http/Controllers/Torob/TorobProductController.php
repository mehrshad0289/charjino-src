<?php

namespace App\Http\Controllers\Torob;

use App\Http\Controllers\Controller;
use App\Services\Torob\TorobProductQueryService;
use App\Services\Torob\TorobV3Cache;
use App\Support\SafeLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TorobProductController extends Controller
{
    // Nginx note (do not change vhosts from this class): api.access.log should include
    // $request_time so Torob 4xx/timeouts can be correlated by latency, e.g.
    // log_format charjino '$remote_addr - $remote_user [$time_local] "$request" '
    //                     '$status $body_bytes_sent "$http_referer" "$http_user_agent" $request_time';
    private const ALLOWED_KEYS = ['page_urls', 'page_uniques', 'page', 'sort', 'cursor'];

    public function __construct(
        private TorobProductQueryService $queryService,
        private TorobV3Cache $torobCache,
    ) {}

    public function products(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            return $this->invalid($request, is_array($payload) ? $payload : [], 'request body is empty or invalid');
        }

        $resolved = $this->resolve($request, $payload);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $ttl = max(1, (int) config('torob.cache_ttl', 300));
        try {
            $body = Cache::remember($this->responseCacheKey($payload), $ttl, function () use ($resolved, $ttl) {
                return $this->dispatch($resolved, $ttl);
            });
        } catch (\Throwable $e) {
            SafeLog::error('torob.v3.products.500', [
                'error' => $e->getMessage(),
                'mode' => is_array($resolved) ? ($resolved['mode'] ?? null) : null,
            ]);
            throw $e;
        }

        return response()->json($body, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|JsonResponse
     */
    private function resolve(Request $request, array $payload): array|JsonResponse
    {
        $unknown = array_diff(array_keys($payload), self::ALLOWED_KEYS);
        if ($unknown !== []) {
            return $this->invalid($request, $payload, 'invalid parameters: '.$this->describeKeys(array_keys($payload)));
        }

        $hasUrls = array_key_exists('page_urls', $payload);
        $hasUniques = array_key_exists('page_uniques', $payload);
        $hasPage = array_key_exists('page', $payload);
        $hasSort = array_key_exists('sort', $payload);
        $hasCursor = array_key_exists('cursor', $payload);

        if (($hasUrls || $hasUniques) && ! $hasPage && ! $hasSort && ! $hasCursor) {
            $urls = [];
            $uniques = [];
            if ($hasUrls) {
                $urls = $payload['page_urls'];
                if (! is_array($urls) || $urls === []) {
                    return $this->invalid($request, $payload, 'page_urls must be a non-empty list');
                }
                if (count($urls) > 100) {
                    return $this->invalid($request, $payload, 'page_urls must not exceed 100 items');
                }
            }
            if ($hasUniques) {
                $uniques = $payload['page_uniques'];
                if (! is_array($uniques) || $uniques === []) {
                    return $this->invalid($request, $payload, 'page_uniques must be a non-empty list');
                }
                if (count($uniques) > 100) {
                    return $this->invalid($request, $payload, 'page_uniques must not exceed 100 items');
                }
            }
            if ($hasUrls && $hasUniques) {
                return ['mode' => 'lookup', 'urls' => $urls, 'uniques' => $uniques];
            }
            if ($hasUrls) {
                return ['mode' => 'urls', 'urls' => $urls];
            }

            return ['mode' => 'uniques', 'uniques' => $uniques];
        }

        if ($hasSort && (string) $payload['sort'] === 'product_id_desc' && ! $hasUrls && ! $hasUniques && ! $hasPage) {
            $cursor = null;
            if ($hasCursor) {
                $raw = $payload['cursor'];
                if (! is_int($raw) && ! ctype_digit((string) $raw)) {
                    return $this->invalid($request, $payload, 'cursor must be an integer');
                }
                $cursor = (int) $raw;
                if ($cursor < 1) {
                    return $this->invalid($request, $payload, 'cursor must be greater than 0');
                }
            }

            return ['mode' => 'cursor', 'cursor' => $cursor];
        }

        if ($hasPage || $hasSort || $hasCursor) {
            if ($hasUrls || $hasUniques || $hasCursor) {
                return $this->invalid($request, $payload, 'invalid combination of parameters');
            }
            if (! $hasPage) {
                return $this->invalid($request, $payload, 'page parameter is not provided');
            }
            if (! $hasSort) {
                return $this->invalid($request, $payload, 'sort parameter is not provided');
            }

            $page = $payload['page'];
            $sort = $payload['sort'];
            if (! is_int($page) && ! ctype_digit((string) $page)) {
                return $this->invalid($request, $payload, 'page must be an integer');
            }
            $page = (int) $page;
            if ($page < 1) {
                return $this->invalid($request, $payload, 'page must be greater than 0');
            }
            if (! in_array($sort, ['date_added_desc', 'date_updated_desc'], true)) {
                return $this->invalid($request, $payload, 'sort parameter is invalid');
            }

            return ['mode' => 'page', 'page' => $page, 'sort' => $sort];
        }

        return $this->invalid($request, $payload, 'invalid parameters: '.$this->describeKeys(array_keys($payload)));
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>
     */
    private function dispatch(array $resolved, int $ttl): array
    {
        if ($resolved['mode'] === 'urls') {
            return $this->queryService->byUrls($resolved['urls']);
        }
        if ($resolved['mode'] === 'uniques') {
            return $this->queryService->byUniques($resolved['uniques']);
        }
        if ($resolved['mode'] === 'lookup') {
            return $this->queryService->byUrlsAndUniques($resolved['urls'], $resolved['uniques']);
        }

        $total = Cache::remember($this->totalCacheKey(), $ttl, fn (): int => $this->queryService->listableTotal());
        if ($resolved['mode'] === 'cursor') {
            return $this->queryService->cursor($resolved['cursor'], $total);
        }

        return $this->queryService->paginated($resolved['page'], $resolved['sort'], $total);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function responseCacheKey(array $payload): string
    {
        // Lookup (page_urls + page_uniques) is covered: both lists are hashed.
        return 'torob:v3:resp:'.hash('sha256', json_encode([
            'api_version' => 'torob_api_v3',
            'schema' => $this->torobCache->schema(),
            'split_rule' => $this->torobCache->ruleFingerprint(),
            'page' => $payload['page'] ?? null,
            'sort' => $payload['sort'] ?? null,
            'cursor' => $payload['cursor'] ?? null,
            'page_urls' => $this->sortedHash($payload['page_urls'] ?? null),
            'page_uniques' => $this->sortedHash($payload['page_uniques'] ?? null),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function totalCacheKey(): string
    {
        return 'torob:v3:total:'.$this->torobCache->schema().':'.$this->torobCache->ruleFingerprint();
    }

    private function sortedHash(mixed $values): string
    {
        if (! is_array($values)) {
            return '';
        }
        $flat = array_values(array_map(static fn (mixed $value): string => (string) $value, $values));
        sort($flat, SORT_STRING);

        return hash('sha256', json_encode($flat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function invalid(Request $request, array $payload, string $message): JsonResponse
    {
        $keys = array_values(array_map(static fn (mixed $key): string => (string) $key, array_keys($payload)));
        $context = [
            'ip' => $request->ip(),
            'keys' => $keys,
            'key_count' => count($keys),
            'error' => $message,
        ];
        if (array_key_exists('page_urls', $payload) && is_array($payload['page_urls'])) {
            $context['page_urls_count'] = count($payload['page_urls']);
        }
        if (array_key_exists('page_uniques', $payload) && is_array($payload['page_uniques'])) {
            $context['page_uniques_count'] = count($payload['page_uniques']);
        }
        SafeLog::warning('torob.v3.products.400', $context);

        return response()->json(['error' => $message], 400);
    }

    /**
     * @param  list<array-key>  $keys
     */
    private function describeKeys(array $keys): string
    {
        return $keys === [] ? 'none' : implode(', ', $keys);
    }
}
