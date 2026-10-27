<?php

namespace App\Modules\BuyGuide;

use App\Models\BuyGuide;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class StorefrontRevalidator
{
    public function schedule(BuyGuide $guide): void
    {
        $tags = $this->tagsFor($guide);
        if ($tags === []) {
            return;
        }
        if (app()->runningUnitTests()) {
            $this->ping($tags);

            return;
        }
        dispatch(function () use ($tags): void {
            $this->ping($tags);
        })->afterResponse();
    }

    /**
     * @param  list<string>  $handles
     */
    public function pingCatalogHandles(array $handles): void
    {
        $clean = array_values(array_unique(array_filter(array_map(
            static fn (mixed $handle): string => trim((string) $handle),
            $handles
        ))));
        if ($clean === []) {
            $this->ping(['sitemap']);

            return;
        }
        foreach ($clean as $handle) {
            $this->ping(['product:'.$handle, 'sitemap'], $handle);
        }
    }

    /**
     * @param  list<string>  $tags
     */
    public function ping(array $tags, ?string $handle = null): void
    {
        $url = rtrim((string) config('services.storefront.url'), '/');
        $secret = (string) config('services.storefront.revalidate_secret');
        if ($url === '' || $secret === '' || ($tags === [] && ! filled($handle))) {
            return;
        }
        $payload = ['tags' => array_values($tags)];
        if (filled($handle)) {
            $payload['handle'] = $handle;
        }
        try {
            $response = Http::timeout(8)
                ->withHeaders(['x-revalidate-secret' => $secret])
                ->post($url.'/api/revalidate', $payload);
            if (! $response->successful()) {
                Log::warning('storefront revalidate failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('storefront revalidate error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @return list<string>
     */
    public function tagsFor(BuyGuide $guide): array
    {
        $tags = ['buy-guide:cat:'.$guide->category];
        if ($guide->scope_type === BuyGuideScope::SUBCATEGORY && $guide->subcategory !== '') {
            $tags[] = 'buy-guide:sub:'.$guide->category.':'.$guide->subcategory;
        }
        if ($guide->scope_type === BuyGuideScope::PRODUCT) {
            $handle = $guide->product?->handle ?: $guide->product()->value('handle');
            if (is_string($handle) && $handle !== '') {
                $tags[] = 'product:'.$handle;
            }
        }

        return array_values(array_unique($tags));
    }
}
