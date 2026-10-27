<?php

namespace App\Modules\Catalog;

use App\Models\Order;
use App\Models\Product;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsSteamGiftShop;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class SteamGiftIndexability
{
    public const TARGET = 500;

    /** @var list<string> */
    public const BLOCKED_TYPES = ['dlc', 'soundtrack', 'demo', 'tool', 'video'];

    /**
     * Free-to-play apps that still have a paid, sellable catalog item (Prime, battle pass, …).
     *
     * @var array<int, string>
     */
    public const FREE_WITH_PAID_ITEMS = [
        730 => 'Prime',
    ];

    /** @var array<int, true> */
    private array $freeAppIds = [];

    public static function hasPaidCatalogItems(int $appId): bool
    {
        return $appId > 0 && isset(self::FREE_WITH_PAID_ITEMS[$appId]);
    }

    public static function paidItemLabel(int $appId): string
    {
        return self::FREE_WITH_PAID_ITEMS[$appId] ?? '';
    }

    /**
     * @return array{indexable: int, blocked: int, free: int, sold: int, popular: int, total: int}
     */
    public function refresh(): array
    {
        $gifts = Product::query()
            ->where('fulfillment_type', FazerCardsIds::STEAM_GIFT)
            ->where('status', 'active')
            ->where('visible', true)
            ->notStorefrontExcluded()
            ->whereHas('skus', fn ($query) => $query->where('status', 'active'))
            ->get(['id', 'handle', 'title', 'name_en', 'fazercards_category_id', 'requirements']);

        $this->freeAppIds = [];
        $soldIds = $this->soldProductIds();
        $scores = $this->popularityScores();
        $popularSet = [];
        foreach (array_keys($scores) as $appId) {
            $popularSet[(int) $appId] = true;
        }
        $basics = $this->basicsFor($gifts, $popularSet, $soldIds);

        $blocked = 0;
        $free = 0;
        $ranked = [];
        $soldKeep = [];
        $paidKeep = [];
        foreach ($gifts as $product) {
            $appId = $this->appIdOf($product);
            $info = $basics[$product->id] ?? ['type' => null, 'is_free' => false];
            $type = $info['type'] ?? $this->blockedTypeFromTitle($this->searchName($product));
            if ($type !== null && in_array($type, self::BLOCKED_TYPES, true)) {
                $blocked++;
                continue;
            }
            if (self::hasPaidCatalogItems($appId)) {
                $paidKeep[$product->id] = true;
                continue;
            }
            if (($info['is_free'] ?? false) === true || ($appId > 0 && isset($this->freeAppIds[$appId]))) {
                $free++;
                continue;
            }
            if (isset($soldIds[$product->id])) {
                $soldKeep[$product->id] = true;
                continue;
            }
            $ranked[] = [
                'id' => $product->id,
                'score' => $appId > 0 ? (int) ($scores[$appId] ?? 0) : 0,
            ];
        }

        usort($ranked, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $keep = array_values(array_unique([...array_keys($soldKeep), ...array_keys($paidKeep)]));
        foreach ($ranked as $row) {
            if (count($keep) >= self::TARGET) {
                break;
            }
            if ($row['score'] <= 0) {
                continue;
            }
            $keep[] = $row['id'];
        }

        DB::table('products')->where('fulfillment_type', FazerCardsIds::STEAM_GIFT)->update(['indexable' => false]);
        foreach (array_chunk($keep, 400) as $chunk) {
            DB::table('products')->whereIn('id', $chunk)->update(['indexable' => true]);
        }

        return [
            'indexable' => count($keep),
            'blocked' => $blocked,
            'free' => $free,
            'sold' => count($soldKeep),
            'popular' => max(0, count($keep) - count($soldKeep)),
            'total' => $gifts->count(),
        ];
    }

    public function blockedTypeFromTitle(string $title): ?string
    {
        $text = trim($title);
        if ($text === '') {
            return null;
        }
        if (preg_match('/\b(soundtrack|sound\s*track|\bost\b|original\s+soundtrack)\b/iu', $text) === 1) {
            return 'soundtrack';
        }
        if (preg_match('/\b(demo|playtest)\b/iu', $text) === 1) {
            return 'demo';
        }
        if (preg_match('/\b(sdk|dedicated\s+server|server\s+files|workshop\s+tools|mod\s*tools|developer\s+tools|editing\s+tools)\b/iu', $text) === 1) {
            return 'tool';
        }
        if (preg_match('/\b(trailer|cinematic|documentary|\bmovie\b|\bvideo\b|live\s+action)\b/iu', $text) === 1) {
            return 'video';
        }
        if (preg_match('/\bdlc\b|season\s+pass|expansion\s+pass/iu', $text) === 1) {
            return 'dlc';
        }

        return null;
    }

    public function steamTypeToBlocked(?string $steamType): ?string
    {
        $type = strtolower(trim((string) $steamType));

        return match ($type) {
            'dlc' => 'dlc',
            'music' => 'soundtrack',
            'demo' => 'demo',
            'tool' => 'tool',
            'video', 'episode', 'series' => 'video',
            default => null,
        };
    }

    public function appIdOf(Product $product): int
    {
        $requirements = is_array($product->requirements) ? $product->requirements : [];
        $fromReq = (int) ($requirements['appid'] ?? 0);
        if ($fromReq > 0) {
            return $fromReq;
        }
        if (preg_match('/:(\d+)$/', (string) $product->fazercards_category_id, $match) === 1) {
            return (int) $match[1];
        }
        if (preg_match('/^steam-gift-(\d+)/', strtolower((string) $product->handle), $match) === 1) {
            return (int) $match[1];
        }

        return 0;
    }

    /**
     * @return array<int, true>
     */
    private function soldProductIds(): array
    {
        $ids = Order::query()
            ->whereNotNull('paid_at')
            ->whereIn('product_id', Product::query()->where('fulfillment_type', FazerCardsIds::STEAM_GIFT)->select('id'))
            ->pluck('product_id');
        $out = [];
        foreach ($ids as $id) {
            $out[(int) $id] = true;
        }

        return $out;
    }

    /**
     * @return array<int, int>
     */
    private function popularityScores(): array
    {
        $scores = [];
        foreach (FazerCardsSteamGiftShop::STAPLE_APP_IDS as $appId) {
            $scores[(int) $appId] = max($scores[(int) $appId] ?? 0, 1_000_000_000);
        }
        foreach ($this->steamSpyScores() as $appId => $score) {
            $scores[$appId] = max($scores[$appId] ?? 0, $score);
        }
        foreach ($this->steamChartAppIds() as $appId => $weight) {
            $scores[$appId] = max($scores[$appId] ?? 0, $weight);
        }

        return $scores;
    }

    /**
     * @return array<int, int>
     */
    private function steamSpyScores(): array
    {
        $scores = [];
        foreach (['top100in2weeks', 'top100forever'] as $request) {
            $payload = $this->getJson('https://steamspy.com/api.php', ['request' => $request]);
            foreach ($payload as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $appId = (int) ($row['appid'] ?? 0);
                if ($appId <= 0) {
                    continue;
                }
                $this->markFreeFromSpy($appId, $row);
                $reviews = (int) ($row['positive'] ?? 0) + (int) ($row['negative'] ?? 0);
                $owners = $this->ownersMidpoint((string) ($row['owners'] ?? ''));
                $ccu = (int) ($row['ccu'] ?? 0);
                $scores[$appId] = max($scores[$appId] ?? 0, ($reviews * 50) + (int) ($owners / 5_000) + ($ccu * 10));
            }
        }
        for ($page = 0; $page <= 39; $page++) {
            $all = $this->getJson('https://steamspy.com/api.php', ['request' => 'all', 'page' => $page]);
            $added = 0;
            foreach ($all as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $appId = (int) ($row['appid'] ?? 0);
                if ($appId <= 0) {
                    continue;
                }
                $this->markFreeFromSpy($appId, $row);
                $reviews = (int) ($row['positive'] ?? 0) + (int) ($row['negative'] ?? 0);
                $owners = $this->ownersMidpoint((string) ($row['owners'] ?? ''));
                $score = ($reviews * 50) + (int) ($owners / 5_000);
                if ($score <= 0) {
                    continue;
                }
                $scores[$appId] = max($scores[$appId] ?? 0, $score);
                $added++;
            }
            if ($added === 0) {
                break;
            }
        }

        return $scores;
    }

    /**
     * @return array<int, int>
     */
    private function steamChartAppIds(): array
    {
        $scores = [];
        $featured = $this->getJson('https://store.steampowered.com/api/featuredcategories', ['cc' => 'us', 'l' => 'english']);
        $items = $featured['top_sellers']['items'] ?? [];
        if (is_array($items)) {
            $rank = count($items);
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $appId = (int) ($item['id'] ?? $item['appid'] ?? 0);
                if ($appId <= 0) {
                    continue;
                }
                $scores[$appId] = max($scores[$appId] ?? 0, 50_000_000 + ($rank * 1000));
                $rank--;
            }
        }
        $charts = $this->getJson('https://api.steampowered.com/ISteamChartsService/GetMostPlayedGames/v1/');
        $ranks = $charts['response']['ranks'] ?? $charts['ranks'] ?? [];
        if (is_array($ranks)) {
            $rank = count($ranks);
            foreach ($ranks as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $appId = (int) ($item['appid'] ?? $item['app_id'] ?? 0);
                if ($appId <= 0) {
                    continue;
                }
                $scores[$appId] = max($scores[$appId] ?? 0, 40_000_000 + ($rank * 1000));
                $rank--;
            }
        }

        return $scores;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $gifts
     * @param  array<int, true>  $popularAppIds
     * @param  array<int, true>  $soldIds
     * @return array<int, array{type: ?string, is_free: bool}>
     */
    private function basicsFor($gifts, array $popularAppIds, array $soldIds): array
    {
        $out = [];
        $fetch = [];
        foreach ($gifts as $product) {
            $fromTitle = $this->blockedTypeFromTitle($this->searchName($product));
            if ($fromTitle !== null) {
                $out[$product->id] = ['type' => $fromTitle, 'is_free' => false];
                continue;
            }
            $appId = $this->appIdOf($product);
            $sold = isset($soldIds[$product->id]);
            $popular = $appId > 0 && isset($popularAppIds[$appId]);
            if ($appId > 0 && ($sold || ($popular && count($fetch) < 800))) {
                $fetch[$product->id] = $appId;
            } else {
                $out[$product->id] = ['type' => null, 'is_free' => false];
            }
        }
        $apps = $this->steamAppBasics(array_values(array_unique(array_values($fetch))));
        foreach ($fetch as $productId => $appId) {
            $basic = $apps[$appId] ?? ['type' => '', 'is_free' => false];
            $steamType = is_string($basic['type'] ?? null) ? strtolower(trim($basic['type'])) : '';
            $out[$productId] = [
                'type' => $steamType !== '' ? $this->steamTypeToBlocked($steamType) : null,
                'is_free' => (bool) ($basic['is_free'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $appIds
     * @return array<int, array{type: string, is_free: bool}>
     */
    private function steamAppBasics(array $appIds): array
    {
        $out = [];
        $missing = [];
        foreach ($appIds as $appId) {
            $cached = Cache::get($this->basicCacheKey($appId));
            if (is_array($cached) && isset($cached['type'])) {
                $isFree = (bool) ($cached['is_free'] ?? false);
                if ($isFree) {
                    $this->freeAppIds[$appId] = true;
                }
                $out[$appId] = [
                    'type' => (string) $cached['type'],
                    'is_free' => $isFree,
                ];
            } else {
                $missing[] = $appId;
            }
        }
        foreach (array_chunk($missing, 20) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk) {
                foreach ($chunk as $appId) {
                    $pool->as((string) $appId)
                        ->timeout(8)
                        ->connectTimeout(4)
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Charjino/1.0)'])
                        ->get('https://store.steampowered.com/api/appdetails', [
                            'appids' => $appId,
                            'filters' => 'basic',
                        ]);
                }
            });
            foreach ($chunk as $appId) {
                $response = $responses[(string) $appId] ?? null;
                if (! is_object($response) || ! method_exists($response, 'successful') || ! $response->successful()) {
                    $response = $this->steamAppDetails($appId);
                }
                if (! is_object($response) || ! method_exists($response, 'successful') || ! $response->successful()) {
                    continue;
                }
                $type = strtolower(trim((string) $response->json($appId.'.data.type')));
                if ($type === '') {
                    continue;
                }
                $isFree = filter_var($response->json($appId.'.data.is_free'), FILTER_VALIDATE_BOOLEAN) === true;
                if ($isFree) {
                    $this->freeAppIds[$appId] = true;
                }
                $row = ['type' => $type, 'is_free' => $isFree];
                $out[$appId] = $row;
                Cache::put($this->basicCacheKey($appId), $row, now()->addDays(30));
            }
        }

        return $out;
    }

    private function basicCacheKey(int $appId): string
    {
        return 'steam-app-basic:'.$appId;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function markFreeFromSpy(int $appId, array $row): void
    {
        $price = $row['price'] ?? $row['initialprice'] ?? null;
        if ($price === 0 || $price === 0.0 || $price === '0') {
            $this->freeAppIds[$appId] = true;
        }
    }

    private function steamAppDetails(int $appId): mixed
    {
        try {
            usleep(150_000);

            return Http::timeout(8)
                ->connectTimeout(4)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Charjino/1.0)'])
                ->get('https://store.steampowered.com/api/appdetails', [
                    'appids' => $appId,
                    'filters' => 'basic',
                    'cc' => 'us',
                    'l' => 'english',
                ]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function searchName(Product $product): string
    {
        return trim($product->title.' '.$product->name_en);
    }

    private function ownersMidpoint(string $raw): int
    {
        $digits = preg_replace('/[^\d.]+/', ' ', $raw) ?? '';
        $parts = array_values(array_filter(explode(' ', trim($digits)), fn ($part) => $part !== ''));
        $nums = array_map(fn ($part) => (int) str_replace('.', '', $part), $parts);
        if ($nums === []) {
            return 0;
        }
        if (count($nums) === 1) {
            return $nums[0];
        }

        return (int) floor(($nums[0] + $nums[1]) / 2);
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function getJson(string $url, array $query = []): array
    {
        try {
            $response = Http::timeout(12)
                ->connectTimeout(5)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Charjino/1.0)'])
                ->get($url, $query);
        } catch (\Throwable) {
            return [];
        }
        if (! $response->successful()) {
            return [];
        }
        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
