<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Modules\Supplier\FazerCards\FazerCardsMedia;
use App\Modules\Supplier\FazerCards\SteamCoverResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class ResolveSteamCoversCommand extends Command
{
    protected $signature = 'charjino:resolve-steam-covers {--limit=0 : حداکثر appid تازه؛ صفر یعنی همه} {--app=* : فقط همین appidها} {--import= : فایل JSON از appid به URL هش‌شده}';

    protected $description = 'کاورهای header.jpg شکستهٔ استیم را با تصویر واقعی فروشگاه استیم عوض می‌کند';

    public function handle(SteamCoverResolver $covers): int
    {
        $import = trim((string) $this->option('import'));
        if ($import !== '') {
            return $this->importMap($covers, $import);
        }

        $limit = max(0, (int) $this->option('limit'));
        $only = array_values(array_filter(array_map('intval', (array) $this->option('app'))));
        $handled = [];
        $fixed = 0;
        $checked = 0;

        Product::query()
            ->where(function ($query): void {
                $query->where('thumbnail', 'like', '%cloudflare.steamstatic.com%')
                    ->orWhere('image_url', 'like', '%cloudflare.steamstatic.com%')
                    ->orWhere('thumbnail', 'like', '%steamstatic.com/steam/apps/%/header.jpg')
                    ->orWhere('image_url', 'like', '%steamstatic.com/steam/apps/%/header.jpg')
                    ->orWhere('thumbnail', 'like', '%steamcdn-a.akamaihd.net/steam/apps/%')
                    ->orWhere('image_url', 'like', '%steamcdn-a.akamaihd.net/steam/apps/%');
            })
            ->select(['id', 'thumbnail', 'image_url'])
            ->chunkByIdDesc(200, function ($products) use ($covers, &$handled, &$fixed, &$checked, $limit, $only) {
                foreach ($products as $product) {
                    $src = (string) ($product->image_url ?: $product->thumbnail);
                    if (! FazerCardsMedia::isLegacySteamHeader($src)) {
                        continue;
                    }
                    $appId = FazerCardsMedia::appIdFromUrl($src);
                    if ($appId <= 0 || ($only !== [] && ! in_array($appId, $only, true))) {
                        continue;
                    }
                    if (! array_key_exists($appId, $handled)) {
                        if ($limit > 0 && $checked >= $limit) {
                            return false;
                        }
                        $cachedBefore = Cache::has(SteamCoverResolver::cacheKey($appId));
                        $resolved = $covers->url($appId, false);
                        $handled[$appId] = $resolved;
                        $checked++;
                        if ($checked % 100 === 0) {
                            $this->line('progress checked='.$checked.' fixed='.$fixed);
                        }
                        if (! $cachedBefore) {
                            usleep(150000);
                        }
                    }
                    $resolved = $handled[$appId];
                    if ($resolved === '' || ! FazerCardsMedia::isHashedSteamHeader($resolved) || $resolved === $src) {
                        continue;
                    }
                    Product::query()->whereKey($product->id)->update([
                        'thumbnail' => $resolved,
                        'image_url' => $resolved,
                    ]);
                    $fixed++;
                }

                return true;
            });

        $this->info('checked='.$checked.' fixed='.$fixed);

        return self::SUCCESS;
    }

    private function importMap(SteamCoverResolver $covers, string $path): int
    {
        if (! File::isReadable($path)) {
            $this->error('import file not readable: '.$path);

            return self::FAILURE;
        }
        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            $this->error('import JSON invalid');

            return self::FAILURE;
        }

        $fixed = 0;
        foreach ($decoded as $appIdRaw => $urlRaw) {
            $appId = (int) $appIdRaw;
            $resolved = $covers->remember($appId, is_string($urlRaw) ? $urlRaw : '');
            if ($resolved === '') {
                continue;
            }
            $legacy = Product::query()
                ->where(function ($query) use ($appId): void {
                    $needle = '/apps/'.$appId.'/';
                    $query->where('thumbnail', 'like', '%'.$needle.'%')
                        ->orWhere('image_url', 'like', '%'.$needle.'%');
                })
                ->select(['id', 'thumbnail', 'image_url'])
                ->get();
            foreach ($legacy as $product) {
                $src = (string) ($product->image_url ?: $product->thumbnail);
                if (! FazerCardsMedia::isLegacySteamHeader($src) || FazerCardsMedia::appIdFromUrl($src) !== $appId) {
                    continue;
                }
                Product::query()->whereKey($product->id)->update([
                    'thumbnail' => $resolved,
                    'image_url' => $resolved,
                ]);
                $fixed++;
            }
        }

        $this->info('imported='.count($decoded).' fixed='.$fixed);

        return self::SUCCESS;
    }
}
