<?php

namespace App\Modules\Catalog;

use App\Models\CatalogChangeAudit;
use App\Models\Product;
use App\Models\UrlRedirect;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CatalogUrlLifecycle
{
    public function unavailableDays(): int
    {
        return max(1, (int) config('charjino.catalog.url_unavailable_days', 14));
    }

    public function syncFromProduct(Product $product): void
    {
        $oldHandle = $product->getOriginal('handle');
        if (is_string($oldHandle) && $oldHandle !== '' && $product->isDirty('handle') && $oldHandle !== $product->handle) {
            $this->putRedirect(
                $this->catalogPath($oldHandle),
                $this->catalogPath((string) $product->handle),
                301,
                'rename',
                'rename',
                $product->id,
            );
            $this->collapseChains($this->catalogPath((string) $product->handle));
        }

        if ($this->isLiveStorefront($product)) {
            $product->unavailable_at = null;
            if ($product->id) {
                $this->deactivateForProduct((int) $product->id, (string) $product->handle);
            }

            return;
        }

        if ($this->isTemporaryUnavailableState($product)) {
            if ($product->unavailable_at === null) {
                $product->unavailable_at = now();
            }
            if ($this->isTemporaryUnavailable($product)) {
                return;
            }
        }

        if ((string) $product->excluded_reason === CatalogExclusion::ADULT_CONTENT) {
            $this->putRedirect(
                $this->catalogPath((string) $product->handle),
                '/catalog/category/steam-gifts',
                301,
                CatalogExclusion::ADULT_CONTENT,
                'exclusion',
                $product->id,
            );

            return;
        }

        if (in_array((string) $product->excluded_reason, [CatalogExclusion::LOGIN_REQUIRED, CatalogExclusion::MANUAL], true)) {
            $target = $this->siblingLive($product);
            if ($target) {
                $this->putRedirect($this->catalogPath((string) $product->handle), $this->catalogPath((string) $target->handle), 301, (string) $product->excluded_reason, 'exclusion', $product->id);
            } else {
                $this->putRedirect($this->catalogPath((string) $product->handle), null, 410, (string) $product->excluded_reason, 'exclusion', $product->id);
            }

            return;
        }

        if (in_array((string) $product->status, ['hidden', 'draft'], true) || ! (bool) $product->visible) {
            $sibling = $this->siblingLive($product);
            $target = $this->substitutePath($product)
                ?? ($sibling ? $this->catalogPath((string) $sibling->handle) : null)
                ?? $this->categoryPath($product);
            $this->putRedirect(
                $this->catalogPath((string) $product->handle),
                $target,
                301,
                $product->substitute_product_id ? 'duplicate' : ($product->status === 'hidden' ? 'admin_hidden' : 'left_storefront'),
                $product->substitute_product_id ? 'merge' : $this->currentSource(),
                $product->id,
            );
        }
    }

    public function auditProduct(Product $product): void
    {
        foreach (['visible', 'status', 'excluded_reason', 'handle'] as $field) {
            if (! $product->wasChanged($field)) {
                continue;
            }
            CatalogChangeAudit::query()->create([
                'product_id' => $product->id,
                'actor_type' => Auth::id() ? 'admin' : 'sync',
                'actor_id' => Auth::id(),
                'source' => $this->currentSource(),
                'field' => $field,
                'before' => $this->scalar($product->getOriginal($field)),
                'after' => $this->scalar($product->getAttribute($field)),
            ]);
        }
    }

    /**
     * @return array{status: string, target: ?string}
     */
    public function resolve(string $path): array
    {
        $normalized = $this->normalizePath($path);
        if ($normalized === '') {
            return ['status' => 'missing', 'target' => null];
        }

        $handle = $this->handleFromPath($normalized);
        $product = $handle !== ''
            ? Product::query()->whereRaw('LOWER(handle) = ?', [mb_strtolower($handle)])->first()
            : null;
        if ($product && ($this->isLiveStorefront($product) || $this->isTemporaryUnavailable($product))) {
            if ($product->id) {
                $this->deactivateForProduct((int) $product->id, (string) $product->handle);
            }

            return ['status' => 'ok', 'target' => null];
        }

        $redirect = $this->activeRedirect($normalized);
        if ($redirect && (int) $redirect->status_code === 410) {
            return ['status' => 'gone', 'target' => null];
        }
        if ($redirect && (int) $redirect->status_code === 301 && is_string($redirect->target_path) && $redirect->target_path !== '') {
            return ['status' => 'redirect', 'target' => $this->finalTarget($redirect->target_path)];
        }

        if ($handle === '') {
            return ['status' => 'ok', 'target' => null];
        }

        if (! $product) {
            $alias = $this->aliasLiveHandle($handle);
            if ($alias) {
                return ['status' => 'redirect', 'target' => $this->catalogPath($alias)];
            }

            return ['status' => 'missing', 'target' => null];
        }
        if ($this->isLiveStorefront($product) || $this->isTemporaryUnavailable($product)) {
            return ['status' => 'ok', 'target' => null];
        }

        $this->syncFromProduct($product);
        $product->saveQuietly();
        $redirect = $this->activeRedirect($normalized);
        if ($redirect && (int) $redirect->status_code === 410) {
            return ['status' => 'gone', 'target' => null];
        }
        if ($redirect && (int) $redirect->status_code === 301 && is_string($redirect->target_path) && $redirect->target_path !== '') {
            return ['status' => 'redirect', 'target' => $this->finalTarget($redirect->target_path)];
        }
        if ($this->isTemporaryUnavailable($product)) {
            return ['status' => 'ok', 'target' => null];
        }

        return ['status' => 'missing', 'target' => null];
    }

    public function expireTemporaryUnavailable(): int
    {
        $cutoff = now()->subDays($this->unavailableDays());
        $count = 0;
        Product::query()
            ->where('status', 'active')
            ->where('visible', false)
            ->where(fn ($query) => $query->whereNull('excluded_reason')->orWhere('excluded_reason', ''))
            ->whereNotNull('unavailable_at')
            ->where('unavailable_at', '<=', $cutoff)
            ->orderBy('id')
            ->each(function (Product $product) use (&$count): void {
                $target = $this->siblingLive($product);
                $path = $target ? $this->catalogPath((string) $target->handle) : $this->categoryPath($product);
                $this->putRedirect($this->catalogPath((string) $product->handle), $path, 301, 'unavailable_expired', 'sync', $product->id);
                $count++;
            });

        return $count;
    }

    public function restoreFalseLoginExclusions(): int
    {
        $count = 0;
        Product::query()
            ->where('excluded_reason', CatalogExclusion::LOGIN_REQUIRED)
            ->orderBy('id')
            ->each(function (Product $product) use (&$count): void {
                $name = trim($product->title.' '.$product->name_en);
                $row = [
                    'category_id' => $product->fazercards_category_id,
                    'id' => $product->fazercards_category_id,
                    'handle' => $product->handle,
                    'name' => $product->title,
                ];
                if (CatalogExclusion::isLoginProduct($name, $row)) {
                    return;
                }
                $product->excluded_reason = null;
                if ($product->status !== 'hidden' && $product->status !== 'draft') {
                    $product->visible = true;
                    $product->status = 'active';
                }
                $product->save();
                $count++;
            });

        return $count;
    }

    public function backfillKnownHandles(array $handles): int
    {
        $count = 0;
        foreach ($handles as $handle) {
            $product = Product::query()->where('handle', $handle)->first();
            if (! $product) {
                continue;
            }
            $this->syncFromProduct($product);
            $product->saveQuietly();
            $count++;
        }

        return $count;
    }

    public function departureCovered(Product $product): bool
    {
        $product->refresh();
        if ($this->isLiveStorefront($product) || $this->isTemporaryUnavailable($product)) {
            return true;
        }

        return UrlRedirect::query()
            ->where('old_path', $this->catalogPath((string) $product->handle))
            ->where('active', true)
            ->whereIn('status_code', [301, 410])
            ->exists();
    }

    public function isTemporaryUnavailable(Product $product): bool
    {
        if (! $this->isTemporaryUnavailableState($product) || $product->unavailable_at === null) {
            return false;
        }
        $at = $product->unavailable_at instanceof Carbon ? $product->unavailable_at : Carbon::parse((string) $product->unavailable_at);

        return $at->gt(now()->subDays($this->unavailableDays()));
    }

    public function catalogPath(string $handle): string
    {
        return '/catalog/'.ltrim($handle, '/');
    }

    public function redirectDuplicate(Product $from, Product $to): void
    {
        $this->putRedirect(
            $this->catalogPath((string) $from->handle),
            $this->catalogPath((string) $to->handle),
            301,
            'duplicate',
            'merge',
            $from->id,
        );
        $this->collapseChains($this->catalogPath((string) $to->handle));
    }

    /**
     * @return list<string>
     */
    public static function departedSampleHandles(): array
    {
        return [
            'pubg-mobile',
            'topup-pubg-mobile-fast',
            'topup-pubg-mobile-manual',
            'manual-call-of-duty-mobile',
            'topup-arknight-endfield-login',
            'topup-genshin-impact-login',
            'topup-love-and-deepspace-login',
            'topup-neverness-to-everness-login',
            'topup-solo-leveling-arise-login',
            'topup-tower-of-fantasy-login',
            'topup-mongil-star-dive',
            'gamekey-deathloop-asia',
            'gamekey-deathloop-latam',
            'gamekey-dragon-ball-z-kakarot-global',
            'gamekey-fallout-76-asia',
            'gamekey-jojos-bizarre-adventure-all-star-battle-r-global',
            'gamekey-lego-batman-legacy-of-the-dark-knight-cis',
            'gamekey-elder-scrolls-4-oblivion-goty-asia',
            'gamekey-marvel-s-spider-man-remastered-cis',
            'gamekey-metro-last-light-redux-cis',
            'gamekey-scarlet-nexus-global',
            'gamekey-tales-of-symphonia-global',
            'gamekey-tales-of-zestiria-global',
            'gamekey-tekken-8-global',
            'giftcard-playstation-nz',
            'giftcard-razer-gold-eu',
            'giftcard-razer-gold-th',
            'giftcard-riot-access-code-se',
            'giftcard-xbox-it',
            'steam-gift-4773940',
        ];
    }

    private function isLiveStorefront(Product $product): bool
    {
        return $product->status === 'active'
            && (bool) $product->visible
            && ! $product->isStorefrontExcluded();
    }

    private function isTemporaryUnavailableState(Product $product): bool
    {
        return $product->status === 'active'
            && ! (bool) $product->visible
            && ! filled($product->excluded_reason)
            && ! $product->isStorefrontExcluded();
    }

    private function putRedirect(string $oldPath, ?string $targetPath, int $code, string $reason, string $source, ?int $productId): void
    {
        if ($targetPath !== null && $this->normalizePath($targetPath) === $this->normalizePath($oldPath)) {
            $targetPath = null;
            $code = 410;
        }
        UrlRedirect::query()->updateOrCreate(
            ['old_path' => $this->normalizePath($oldPath)],
            [
                'target_path' => $targetPath ? $this->normalizePath($targetPath) : null,
                'status_code' => $code,
                'reason' => $reason,
                'source' => $source,
                'active' => true,
                'product_id' => $productId,
            ],
        );
        if ($code === 301 && $targetPath) {
            $this->collapseChains($this->normalizePath($targetPath));
        }
    }

    private function collapseChains(string $finalPath): void
    {
        $final = $this->normalizePath($finalPath);
        UrlRedirect::query()
            ->where('active', true)
            ->where('target_path', $final)
            ->each(function (UrlRedirect $row) use ($final): void {
                UrlRedirect::query()
                    ->where('active', true)
                    ->where('target_path', $row->old_path)
                    ->update(['target_path' => $final]);
            });
    }

    private function deactivateForProduct(int $productId, string $handle): void
    {
        UrlRedirect::query()
            ->where('active', true)
            ->where(function ($query) use ($productId, $handle) {
                $query->where('product_id', $productId)
                    ->orWhere('old_path', $this->catalogPath($handle));
            })
            ->update(['active' => false]);
    }

    private function activeRedirect(string $path): ?UrlRedirect
    {
        return UrlRedirect::query()
            ->where('old_path', $path)
            ->where('active', true)
            ->orderByDesc('id')
            ->first();
    }

    private function finalTarget(string $target): string
    {
        $seen = [];
        $current = $this->normalizePath($target);
        while (! isset($seen[$current])) {
            $seen[$current] = true;
            $next = $this->activeRedirect($current);
            if (! $next || (int) $next->status_code !== 301 || ! is_string($next->target_path) || $next->target_path === '') {
                return $current;
            }
            $current = $this->normalizePath($next->target_path);
        }

        return $current;
    }

    public function liveAlternate(Product $product): ?Product
    {
        return $this->siblingLive($product);
    }

    private function siblingLive(Product $product): ?Product
    {
        $stem = $this->gameStem((string) $product->handle);
        if ($stem === '') {
            return null;
        }
        $query = Product::query()
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->where('visible', true)
            ->where(fn ($inner) => $inner->whereNull('excluded_reason')->orWhere('excluded_reason', ''))
            ->where('handle', 'not like', '%-login')
            ->where('handle', 'not like', 'manual-%');
        if ($product->catalog_group_id) {
            $query->where('catalog_group_id', $product->catalog_group_id);
        }
        $matches = $query
            ->where('handle', 'like', '%'.$stem.'%')
            ->orderByRaw('CASE WHEN handle LIKE ? THEN 0 ELSE 1 END', ['%reserve%'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return $matches->first();
    }

    private function substitutePath(Product $product): ?string
    {
        $id = (int) ($product->substitute_product_id ?? 0);
        if ($id < 1) {
            return null;
        }
        $target = Product::query()->where('id', $id)->where('status', 'active')->where('visible', true)->first();

        return $target ? $this->catalogPath((string) $target->handle) : null;
    }

    private function categoryPath(Product $product): string
    {
        $slug = $product->catalogGroup?->slug;
        if (! is_string($slug) || $slug === '') {
            $product->loadMissing('catalogGroup');
            $slug = $product->catalogGroup?->slug;
        }
        if (is_string($slug) && $slug !== '') {
            return '/catalog/category/'.$slug;
        }

        return '/catalog/category';
    }

    private function gameStem(string $handle): string
    {
        $stem = strtolower(trim($handle));
        $stem = preg_replace('/^(topup-|gamekey-|giftcard-|manual-|steam-gift-)/', '', $stem) ?? $stem;
        $stem = preg_replace('/-(fast|manual|reserve|login|auto|global|asia|latam|cis|eu|nz|th|it|se)$/', '', $stem) ?? $stem;

        return trim($stem, '-');
    }

    private function aliasLiveHandle(string $handle): ?string
    {
        $needle = mb_strtolower($handle);
        if ($needle === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $needle)) {
            return null;
        }

        $pickUnique = function ($query) {
            $handles = $query->limit(2)->pluck('handle');

            return $handles->count() === 1 ? (string) $handles->first() : null;
        };

        $base = Product::query()
            ->where('status', 'active')
            ->where('visible', true);

        $prefixed = $pickUnique(
            (clone $base)->where(function ($query) use ($needle): void {
                $query->whereRaw('LOWER(handle) = ?', ['topup-'.$needle])
                    ->orWhereRaw('LOWER(handle) = ?', ['giftcard-'.$needle]);
            })
        );
        if ($prefixed) {
            return $prefixed;
        }

        $starts = $pickUnique(
            (clone $base)->whereRaw('LOWER(handle) LIKE ?', [$needle.'-%'])->orderByRaw('LENGTH(handle)')
        );
        if ($starts) {
            return $starts;
        }

        return $pickUnique(
            (clone $base)->whereRaw('LOWER(handle) LIKE ?', ['%-'.$needle])->orderByRaw('LENGTH(handle)')
        );
    }

    private function handleFromPath(string $path): string
    {
        if (preg_match('#^/catalog/([^/]+)$#', $path, $match) !== 1) {
            return '';
        }
        if ($match[1] === 'category') {
            return '';
        }

        return $match[1];
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        $parts = parse_url($path);
        $raw = is_array($parts) && isset($parts['path']) ? (string) $parts['path'] : $path;
        $raw = '/'.ltrim($raw, '/');

        $trimmed = rtrim($raw, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    private function currentSource(): string
    {
        if (Auth::id()) {
            return 'admin';
        }
        if (app()->runningInConsole()) {
            return 'sync';
        }

        return 'system';
    }

    private function scalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
