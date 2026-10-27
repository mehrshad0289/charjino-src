<?php

namespace App\Modules\Catalog;

use App\Exceptions\ApiException;
use App\Jobs\SmartSearch\SyncProductSearchDocuments;
use App\Models\CatalogGroup;
use App\Models\FazerCardsSyncCheckpoint;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\Pricing\PricingService;
use App\Modules\SmartSearch\SmartSearchSyncService;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Modules\Supplier\FazerCards\FazerCardsCatalogScopes;
use App\Modules\Supplier\FazerCards\FazerCardsClient;
use App\Modules\Supplier\FazerCards\FazerCardsContentionRetry;
use App\Modules\Supplier\FazerCards\FazerCardsHeavySyncLock;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsListedShop;
use App\Modules\Supplier\FazerCards\FazerCardsMedia;
use App\Modules\Supplier\FazerCards\FazerCardsPayload;
use App\Modules\Supplier\FazerCards\FazerCardsRequestLane;
use App\Modules\Supplier\FazerCards\FazerCardsSeeds;
use App\Modules\Supplier\FazerCards\FazerCardsSkuSyncFailure;
use App\Modules\Supplier\FazerCards\FazerCardsSteamGiftCatalog;
use App\Modules\Supplier\FazerCards\FazerCardsSteamGiftQuotes;
use App\Modules\Supplier\FazerCards\FazerCardsSteamGiftShop;
use App\Modules\Supplier\FazerCards\FazerCardsSteamTopupQuotes;
use App\Modules\Supplier\FazerCards\FazerCardsSyncMetrics;
use App\Modules\Supplier\FazerCards\FazerCardsSyncStatus;
use App\Modules\Supplier\FazerCards\FazerCardsTelegramQuotes;
use App\Modules\Supplier\FazerCards\FazerCardsValidateId;
use App\Modules\Supplier\FazerCards\SteamCoverResolver;
use App\Modules\Supplier\Shop2\ForbiddenBuyerField;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FazerCardsCatalogSync
{
    /** @var list<string> */
    private const PHASES = [
        'topups',
        'giftcards',
        'gamekeys',
        'telegram',
        'steam-topup',
        'funpay',
        'manual',
        'finalize',
    ];

    /** @var array<string, true> */
    private array $seen = [];

    /** @var array<string, true> */
    private array $touchedCategoryKeys = [];

    /** @var list<array<string, mixed>> */
    private array $syncIncomplete = [];

    private int $productsUpserted = 0;

    private int $skusUpserted = 0;

    private int $hiddenProducts = 0;

    private int $restoredProducts = 0;

    /** @var list<string>|null */
    private ?array $validateSupportedIds = null;

    private ?FazerCardsHeavySyncLock $heavyLock = null;

    private string $steamMode = 'full';

    private bool $stopAfterCurrent = false;

    private string $stopReason = '';

    private int $rejectedProducts = 0;

    public function __construct(
        private readonly FazerCardsClient $client,
        private readonly FazerCardsAdapter $adapter,
        private readonly PricingService $pricing,
        private readonly FazerCardsSteamGiftCatalog $steamGifts,
        private readonly FazerCardsSteamGiftShop $steamGiftShop,
        private readonly FazerCardsListedShop $listedShop,
        private readonly SteamCoverResolver $steamCovers,
    ) {}

    /**
     * Full catalog in CLI / tests / scheduler. PHP-FPM must not call this.
     *
     * @return array<string, mixed>
     */
    public function sync(?string $family = null, string $mode = 'full'): array
    {
        $this->steamMode = $this->normalizeSteamMode($mode);

        return StorefrontHomeCache::defer(fn () => $this->runFullSync($family));
    }

    /**
     * @return array<string, mixed>
     */
    private function runFullSync(?string $family): array
    {
        if ($family !== null && $family !== '' && ! FazerCardsCatalogScopes::isKnown($family)) {
            throw new ApiException('این دسته در کاتالوگ FazerCards نیست.', 422, 'INVALID_CATALOG_SCOPE');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        if ($this->steamMode === 'visible' && FazerCardsSyncCheckpoint::weeklyRunning()) {
            return [
                'status' => 'skipped',
                'scope' => 'steam-gifts',
                'products' => 0,
                'skus' => 0,
                'message' => 'همگام هفتگی گیفت استیم در جریان است.',
            ];
        }
        if ($family === 'steam-gifts' && FazerCardsSyncCheckpoint::weeklyRunning()) {
            $this->steamMode = 'weekly';
        }
        $this->heavyLock = FazerCardsHeavySyncLock::acquire();
        if ($this->heavyLock === null) {
            $current = FazerCardsSyncStatus::get();
            $current['message'] = 'همگام در حال اجراست.';

            return $current;
        }
        FazerCardsSyncMetrics::start();
        $result = ['status' => 'idle'];
        try {
            return FazerCardsRequestLane::using(FazerCardsRequestLane::HEAVY, function () use ($family, &$result) {
                if ($this->steamMode !== 'weekly') {
                    $this->forgetRunIfOwned();
                }
                $stalls = 0;
                do {
                    if ($this->heavyLock === null || ! $this->heavyLock->heartbeat()) {
                        FazerCardsHeavySyncLock::logLost('heartbeat');
                        $this->stopAfterCurrent = true;
                        $this->stopReason = 'lock_lost';
                        $state = FazerCardsSyncStatus::state();
                        $state['message'] = 'قفل همگام از دست رفت.';
                        FazerCardsSyncStatus::putState($this->withHeartbeat($state));

                        return FazerCardsSyncStatus::publicView($state);
                    }
                    $result = $this->tick(null, null, $family, true);
                    if ($this->stopAfterCurrent) {
                        break;
                    }
                    $paused = str_contains((string) ($result['message'] ?? ''), 'قطع');
                    $busy = str_contains((string) ($result['message'] ?? ''), 'در حال اجراست');
                    if ($paused || $busy) {
                        $stalls++;
                        if ($stalls >= 8) {
                            throw new ApiException('اتصال به FazerCards برقرار نشد.', 503, 'SUPPLIER_UNREACHABLE');
                        }
                        if (! app()->environment('testing')) {
                            sleep(min(8, $stalls + 1));
                        }

                        continue;
                    }
                    $stalls = 0;
                } while (($result['status'] ?? '') === 'running');

                return $result;
            });
        } finally {
            FazerCardsSyncMetrics::finish((string) ($family ?? 'all'), (string) ($result['status'] ?? ''), [
                'products' => $this->productsUpserted,
                'skus' => $this->skusUpserted,
                'rejected' => $this->rejectedProducts,
                'steam_mode' => $this->steamMode,
                'stop_reason' => $this->stopReason,
            ]);
            $this->heavyLock?->release();
            $this->heavyLock = null;
        }
    }

    /**
     * قیمت و موجودی محصولات فعال gift/key/topup و نرخ استیم.
     *
     * @return array<string, mixed>
     */
    public function lightRefresh(): array
    {
        FazerCardsSyncMetrics::start();
        $result = [
            'status' => 'idle',
            'scope' => 'light',
            'products' => 0,
            'skus' => 0,
            'failed' => 0,
        ];
        try {
            $result = FazerCardsRequestLane::using(
                FazerCardsRequestLane::LIGHT,
                fn (): array => StorefrontHomeCache::defer(function (): array {
                    $this->seen = [];
                    $this->touchedCategoryKeys = [];
                    $this->syncIncomplete = [];
                    $this->productsUpserted = 0;
                    $this->skusUpserted = 0;
                    $this->hiddenProducts = 0;
                    $this->restoredProducts = 0;
                    $failed = 0;
                    try {
                        $this->syncSteamTopup();
                    } catch (ApiException $e) {
                        $failed++;
                        Log::warning('fazercards light steam topup failed', [
                            'code' => $e->errorCode,
                            'message' => $e->getMessage(),
                        ]);
                    }
                    $map = [
                        FazerCardsIds::TOPUP => $this->familySpec('topups'),
                        FazerCardsIds::GIFTCARD => $this->familySpec('giftcards'),
                        FazerCardsIds::GAMEKEY => $this->familySpec('gamekeys'),
                    ];
                    Product::query()
                        ->where('visible', true)
                        ->where(fn ($query) => $query->whereNull('excluded_reason')->orWhere('excluded_reason', ''))
                        ->whereIn('fulfillment_type', [FazerCardsIds::TOPUP, FazerCardsIds::GIFTCARD, FazerCardsIds::GAMEKEY])
                        ->whereNotNull('fazercards_category_id')
                        ->where('fazercards_category_id', '!=', '')
                        ->orderBy('id')
                        ->chunkById(80, function ($products) use ($map, &$failed): void {
                            foreach ($products as $product) {
                                $family = FazerCardsIds::familyOf($product->fulfillment_type, $product->fazercards_category_id);
                                $spec = $map[$family] ?? null;
                                if (! is_array($spec)) {
                                    continue;
                                }
                                $remoteId = FazerCardsIds::rawCategory((string) $product->fazercards_category_id);
                                try {
                                    $this->ingestListedRow($spec, [
                                        'category_id' => $remoteId,
                                        'game_id' => $remoteId,
                                        'id' => $remoteId,
                                        'name' => (string) ($product->name_en ?: $product->title),
                                    ]);
                                } catch (ApiException $e) {
                                    $failed++;
                                    Log::warning('fazercards light product failed', [
                                        'product_id' => $product->id,
                                        'handle' => $product->handle,
                                        'code' => $e->errorCode,
                                        'message' => $e->getMessage(),
                                    ]);
                                }
                            }
                        });

                    Log::info('fazercards light finished', [
                        'ok' => $failed === 0,
                        'products' => $this->productsUpserted,
                        'skus' => $this->skusUpserted,
                        'failed' => $failed,
                        'hiddenProducts' => $this->hiddenProducts,
                        'restoredProducts' => $this->restoredProducts,
                    ]);

                    return [
                        'status' => $failed > 0 && $this->productsUpserted < 1 ? 'failed' : 'completed',
                        'scope' => 'light',
                        'products' => $this->productsUpserted,
                        'skus' => $this->skusUpserted,
                        'failed' => $failed,
                        'hiddenProducts' => $this->hiddenProducts,
                        'restoredProducts' => $this->restoredProducts,
                        'message' => $failed > 0
                            ? 'قیمت و موجودی بخشی از محصولات به‌روز شد؛ '.$failed.' مورد رد شد.'
                            : 'قیمت و موجودی محصولات فعال به‌روز شد.',
                    ];
                })
            );
        } finally {
            FazerCardsSyncMetrics::finish('light', (string) ($result['status'] ?? ''), [
                'products' => (int) ($result['products'] ?? 0),
                'skus' => (int) ($result['skus'] ?? 0),
                'failed' => (int) ($result['failed'] ?? 0),
            ]);
        }

        return $result;
    }

    /**
     * One slice for HTTP. Stays under PHP max_execution_time.
     * `$family` مثل topups/giftcards؛ خالی یعنی ادامهٔ اجرای جاری، یا شروع همه اگر بیکار است.
     *
     * @return array<string, mixed>
     */
    public function tick(?int $budgetMs = null, ?int $maxItems = null, ?string $family = null, bool $cliBurst = false, string $mode = 'full'): array
    {
        if ($this->heavyLock === null) {
            $this->steamMode = $this->normalizeSteamMode($mode);
        }

        return $this->runTickSlice($budgetMs, $maxItems, $family, $cliBurst);
    }

    /**
     * @return array<string, mixed>
     */
    private function runTickSlice(?int $budgetMs, ?int $maxItems, ?string $family, bool $cliBurst): array
    {
        if (! $this->client->configured()) {
            throw new ApiException('کلید FazerCards تنظیم نشده است.', 503, 'SUPPLIER_NOT_CONFIGURED');
        }

        $budgetMs = $budgetMs ?? (int) config('charjino.supplier.fazercards_sync_budget_ms', 20000);
        $maxItems = $maxItems ?? (int) config('charjino.supplier.fazercards_sync_max_per_tick', 5);
        $budgetMs = max(3000, min(45000, $budgetMs));
        $maxItems = max(1, min(20, $maxItems));
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $ownsLock = $this->heavyLock !== null;
        if (! $ownsLock) {
            $this->heavyLock = FazerCardsHeavySyncLock::acquire();
            if ($this->heavyLock === null) {
                $current = FazerCardsSyncStatus::get();
                $current['message'] = 'همگام در حال اجراست.';

                return $current;
            }
        }

        SmartSearchSyncService::$bulkQuiet = true;
        $state = FazerCardsSyncStatus::state();
        try {
            return StorefrontHomeCache::defer(function () use (&$budgetMs, &$maxItems, $family, $cliBurst, &$state): array {
                if (FazerCardsSyncStatus::cancelRequested()) {
                    return FazerCardsSyncStatus::markCancelled();
                }
                $requested = FazerCardsCatalogScopes::normalize($family);
                if ($requested !== null && $requested !== FazerCardsCatalogScopes::ALL && ! FazerCardsCatalogScopes::isKnown($requested)) {
                    throw new ApiException('این دسته در کاتالوگ FazerCards نیست.', 422, 'INVALID_CATALOG_SCOPE');
                }
                if ($this->shouldResume($state)) {
                    $current = (string) ($state['scope'] ?? FazerCardsCatalogScopes::ALL);
                    if ($requested !== null && $requested !== $current && ($state['status'] ?? '') === 'running') {
                        $view = FazerCardsSyncStatus::publicView($state);
                        $view['message'] = 'اول همگام «'.((string) ($state['scopeLabel'] ?? $current)).'» تمام شود. یا لغو کن.';

                        return $view;
                    }
                    if ($requested !== null && $requested !== $current) {
                        $state = $this->bootState($requested);
                    } else {
                        $wait = FazerCardsSyncStatus::retryAfterSec($state);
                        if ($wait > 0) {
                            $paused = $this->storeCounters($state);
                            $paused['message'] = 'صبر کن؛ محدودیت نرخ فهرست گیفت استیم هنوز فعال است. حدود '.$wait.' ثانیه بعد ادامه می‌دهیم. لغو نزن.';
                            FazerCardsSyncStatus::putState($this->withHeartbeat($paused));

                            return FazerCardsSyncStatus::publicView($paused);
                        }
                        $state['status'] = 'running';
                        unset($state['errorCode'], $state['finishedAt'], $state['retryAfterAt']);
                    }
                } else {
                    $state = $this->bootState($requested);
                }
                $this->loadCounters($state);
                $state = $this->storeCounters($state);
                FazerCardsSyncStatus::putState($this->withHeartbeat($state));
                $phase = (string) ($state['phase'] ?? '');
                if ($phase === 'steam-gifts' || $requested === 'steam-gifts') {
                    $maxItems = $cliBurst ? max($maxItems, 15) : 1;
                } elseif ($cliBurst && in_array($phase, ['topups', 'giftcards', 'gamekeys', 'manual'], true)) {
                    $maxItems = max($maxItems, 15);
                }
                $deadline = microtime(true) + ($budgetMs / 1000);
                $used = 0;
                while (($state['status'] ?? '') === 'running' && $used < $maxItems && microtime(true) < $deadline) {
                    if ($this->heavyLock === null || ! $this->heavyLock->heartbeat()) {
                        FazerCardsHeavySyncLock::logLost('heartbeat');
                        $this->stopAfterCurrent = true;
                        $this->stopReason = 'lock_lost';
                        $state['message'] = 'قفل همگام از دست رفت.';
                        FazerCardsSyncStatus::putState($this->withHeartbeat($state));

                        return FazerCardsSyncStatus::publicView($state);
                    }
                    if (FazerCardsSyncStatus::cancelRequested()) {
                        return FazerCardsSyncStatus::markCancelled();
                    }
                    $before = $state;
                    $state = $this->advance($state);
                    $used++;
                    $state = $this->storeCounters($state);
                    FazerCardsSyncStatus::putState($this->withHeartbeat($state));
                    if ($this->stopAfterCurrent) {
                        return FazerCardsSyncStatus::publicView($state);
                    }
                    if (($state['status'] ?? '') !== 'running') {
                        break;
                    }
                    if (($state['phase'] ?? '') === ($before['phase'] ?? '')
                        && (int) ($state['pageIndex'] ?? 0) === (int) ($before['pageIndex'] ?? 0)
                        && (string) ($state['listCursor'] ?? '') === (string) ($before['listCursor'] ?? '')
                        && ($state['pageItems'] ?? []) === ($before['pageItems'] ?? [])
                    ) {
                        break;
                    }
                }

                return FazerCardsSyncStatus::publicView($state);
            });
        } catch (ApiException $e) {
            $checkpoint = $this->storeCounters($state);
            $phase = (string) ($checkpoint['phase'] ?? '');
            if (in_array($e->errorCode, ['SUPPLIER_UNREACHABLE', 'SUPPLIER_UNAVAILABLE', 'RATE_LIMIT_EXCEEDED'], true)
                && $phase !== '' && $phase !== 'done'
            ) {
                $wait = $e->errorCode === 'RATE_LIMIT_EXCEEDED' ? 180 : 25;
                $checkpoint['status'] = 'running';
                $checkpoint['retryAfterAt'] = now()->addSeconds($wait)->toIso8601String();
                $checkpoint['message'] = $e->errorCode === 'RATE_LIMIT_EXCEEDED'
                    ? 'محدودیت نرخ فهرست گیفت استیم (۱ درخواست هر ۳ دقیقه). حدود ۳ دقیقه صبر کن؛ صفحه را نبند و لغو نزن.'
                    : 'اتصال لحظه‌ای قطع شد. از همین نقطه ادامه بده.';
                unset($checkpoint['errorCode'], $checkpoint['finishedAt']);
                FazerCardsSyncStatus::putState($this->withHeartbeat($checkpoint));

                return FazerCardsSyncStatus::publicView($checkpoint);
            }
            if (! in_array($e->errorCode, ['SUPPLIER_UNAVAILABLE', 'SUPPLIER_NOT_CONFIGURED', 'SUPPLIER_UNREACHABLE'], true)) {
                FazerCardsSyncStatus::putState(array_merge($checkpoint, [
                    'status' => 'failed',
                    'finishedAt' => now()->toIso8601String(),
                    'errorCode' => $e->errorCode,
                    'message' => $e->getMessage(),
                    'products' => $this->productsUpserted,
                    'skus' => $this->skusUpserted,
                ]));
            }

            throw $e;
        } finally {
            SmartSearchSyncService::$bulkQuiet = false;
            if (! $ownsLock) {
                $this->heavyLock?->release();
                $this->heavyLock = null;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(): array
    {
        FazerCardsSyncStatus::requestCancel();
        $lock = FazerCardsHeavySyncLock::acquire();
        if ($lock !== null) {
            try {
                return FazerCardsSyncStatus::markCancelled();
            } finally {
                $lock->release();
            }
        }
        $current = FazerCardsSyncStatus::get();
        $current['message'] = 'لغو ثبت شد. تکهٔ جاری تمام شود، بعد می‌ایستد.';

        return $current;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function shouldResume(array $state): bool
    {
        $status = (string) ($state['status'] ?? '');
        if ($status === 'cancelled' || $status === 'completed' || $status === 'idle') {
            return false;
        }
        if ($status === 'running') {
            return true;
        }
        if ($status !== 'failed') {
            return false;
        }
        $phase = (string) ($state['phase'] ?? '');

        return $phase !== '' && $phase !== 'done';
    }

    /**
     * @return array<string, mixed>
     */
    private function bootState(?string $scopeId = null): array
    {
        $started = now();
        try {
            $account = $this->publicAccount($this->adapter->account());
        } catch (ApiException $e) {
            FazerCardsSyncStatus::fail($e->errorCode ?? 'SUPPLIER_UNAVAILABLE', $e->getMessage(), $e->status);
        }

        $this->seen = [];
        $this->touchedCategoryKeys = [];
        $this->syncIncomplete = [];
        $this->productsUpserted = 0;
        $this->skusUpserted = 0;
        $this->hiddenProducts = 0;
        $this->restoredProducts = 0;
        $scope = FazerCardsCatalogScopes::resolve($scopeId);
        $phases = $scope['id'] === FazerCardsCatalogScopes::ALL
            ? self::PHASES
            : array_merge($scope['phases'], ['finalize']);
        if (in_array('steam-gifts', $phases, true)) {
            $this->steamGifts->invalidate();
        }

        return $this->withHeartbeat([
            'status' => 'running',
            'scope' => $scope['id'],
            'scopeLabel' => $scope['title'],
            'phases' => $phases,
            'families' => $scope['families'],
            'phase' => $phases[0],
            'phaseLabel' => $this->phaseLabel($phases[0]),
            'startedAt' => $started->toIso8601String(),
            'account' => $account,
            'listCursor' => '',
            'pageItems' => [],
            'pageIndex' => 0,
            'hasMore' => false,
            'seen' => [],
            'touched' => [],
            'products' => 0,
            'skus' => 0,
            'processedItems' => 0,
            'lockOwner' => $this->heavyLock?->owner(),
            'steamMode' => $this->steamMode,
            'message' => 'شروع همگام «'.$scope['title'].'».',
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function advance(array $state): array
    {
        $phase = (string) ($state['phase'] ?? 'topups');
        $state['phaseLabel'] = $this->phaseLabel($phase);
        $state['message'] = 'در حال همگام: '.$state['phaseLabel'];

        if ($phase === 'telegram') {
            $this->syncTelegram();

            return $this->nextPhase($state);
        }
        if ($phase === 'steam-topup') {
            $this->syncSteamTopup();

            return $this->nextPhase($state);
        }
        if ($phase === 'funpay') {
            return $this->syncFunpay($state);
        }
        if ($phase === 'finalize') {
            return $this->finalize($state);
        }

        $spec = $this->familySpec($phase);
        if ($spec === null) {
            return $this->nextPhase($state);
        }
        if (($spec['family'] ?? '') === FazerCardsIds::STEAM_GIFT) {
            if (($state['scope'] ?? '') === FazerCardsCatalogScopes::ALL) {
                return $this->nextPhase($state);
            }

            return $this->advanceSteamGifts($state, $spec);
        }

        return $this->advanceListedFamily($state, $spec);
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function advanceSteamGifts(array $state, array $spec): array
    {
        $this->steamGiftShop->useQueue($this->steamMode === 'visible' ? 'visible' : 'full');
        if ($this->steamMode === 'weekly' && $this->pastWeeklyStopHour()) {
            FazerCardsSyncCheckpoint::saveWeekly((int) ($state['pageIndex'] ?? 0), (string) ($state['lastSteamAppid'] ?? ''));
            $this->stopAfterCurrent = true;
            $this->stopReason = 'weekly_window';
            $state['message'] = 'توقف امن ۰۸:۰۰؛ از همین نقطه ادامه می‌دهیم.';

            return $state;
        }
        if ($this->heavyLock === null || ! $this->heavyLock->stillOwned()) {
            FazerCardsHeavySyncLock::logLost('ownership');
            $this->stopAfterCurrent = true;
            $this->stopReason = 'lock_lost';
            $state['message'] = 'قفل همگام از دست رفت.';

            return $state;
        }
        if (empty($state['steamQueueReady']) || $this->steamGiftShop->queueTotal() < 1) {
            if ($this->steamMode === 'visible') {
                $this->steamGifts->invalidate();
                $this->steamGifts->total();
                $total = $this->steamGiftShop->prepareVisibleWorklist();
            } else {
                $total = $this->steamGiftShop->prepareWorklist();
            }
            $state['steamQueueReady'] = true;
            $state['pageIndex'] = 0;
            $state['listCursor'] = '';
            $state['pageItems'] = [];
            $state['catalogTotal'] = $total;
            if ($this->steamMode === 'weekly') {
                $checkpoint = FazerCardsSyncCheckpoint::weekly();
                if ($checkpoint) {
                    $state['pageIndex'] = (int) $checkpoint->last_index;
                    $state['lastSteamAppid'] = (string) ($checkpoint->last_processed_id ?? '');
                }
            }
        }
        $index = (int) ($state['pageIndex'] ?? 0);
        $total = $this->steamGiftShop->queueTotal();
        $state['catalogTotal'] = $total;
        $state['pageItems'] = [];
        $state['hasMore'] = $index < $total;
        if ($total < 1 || $index >= $total) {
            if ($this->steamMode === 'visible') {
                $this->steamGiftShop->curate();
            }
            if ($this->steamMode === 'weekly') {
                FazerCardsSyncCheckpoint::clearWeekly();
            }
            SteamGiftBlocklist::applyExisting();

            return $this->nextPhase($state);
        }
        $row = $this->steamGiftShop->queueAt($index) ?? [];
        $ok = $this->ingestListedRow($spec, $row);
        $owned = $this->heavyLock !== null && $this->heavyLock->stillOwned();
        if ($ok && $owned) {
            $this->revealSteamGift((int) ($row['appid'] ?? $row['app_id'] ?? 0));
        }
        $state['pageIndex'] = $index + 1;
        $state['lastSteamAppid'] = (string) ((int) ($row['appid'] ?? $row['app_id'] ?? 0));
        $state['processedItems'] = (int) ($state['processedItems'] ?? 0) + 1;
        $state['hasMore'] = $state['pageIndex'] < $total;
        $state['message'] = 'در حال همگام: گیفت استیم منتخب · '.$state['pageIndex'].' از '.$total;
        if ($this->steamMode === 'weekly') {
            FazerCardsSyncCheckpoint::saveWeekly((int) $state['pageIndex'], (string) $state['lastSteamAppid']);
        }
        if (! $owned) {
            FazerCardsHeavySyncLock::logLost('ownership');
            $this->stopAfterCurrent = true;
            $this->stopReason = 'lock_lost';
            $state['message'] = 'قفل همگام از دست رفت.';

            return $state;
        }
        if (! $state['hasMore']) {
            if ($this->steamMode === 'visible') {
                $this->steamGiftShop->curate();
            }
            if ($this->steamMode === 'weekly') {
                FazerCardsSyncCheckpoint::clearWeekly();
            }
            SteamGiftBlocklist::applyExisting();

            return $this->nextPhase($state);
        }

        return $state;
    }

    public function importSteamGift(int $appId): Product
    {
        $spec = $this->familySpec('steam-gifts');
        if ($spec === null) {
            throw new ApiException('دسته گیفت استیم پیدا نشد.', 422, 'INVALID_CATALOG_SCOPE');
        }
        $row = $this->steamGifts->findApp($appId) ?? ['appid' => $appId, 'name' => ''];
        $preview = trim((string) ($row['name'] ?? '').' '.$appId);
        if (SteamGiftBlocklist::matches($preview)) {
            SteamGiftBlocklist::excludeNamed($preview, 'steam-gift-'.$appId, (string) $appId);
            throw new ApiException('این عنوان با فهرست کلمات ممنوعه گیفت استیم هم‌خوان است.', 422, 'ADULT_BLOCKED');
        }
        $this->ingestListedRow($spec, $row);
        $product = Product::query()->where('fazercards_category_id', FazerCardsIds::productKey(FazerCardsIds::STEAM_GIFT, (string) $appId))->first();
        if (! $product) {
            throw new ApiException('این بازی در کاتالوگ گیفت Fazer قابل فروش نیست.', 404, 'NOT_FOUND');
        }
        $requirements = is_array($product->requirements) ? $product->requirements : [];
        $requirements['shopPinned'] = true;
        $product->requirements = $requirements;
        $product->status = 'active';
        if (! $product->isStorefrontExcluded()) {
            $product->visible = true;
        }
        $product->save();

        return $product;
    }

    public function importListed(string $scopeId, string $remoteId): Product
    {
        $scopeId = strtolower(trim($scopeId));
        $remoteId = trim($remoteId);
        if ($scopeId === 'steam-gifts') {
            return $this->importSteamGift((int) $remoteId);
        }
        $spec = $this->familySpec($scopeId);
        if ($spec === null || ! in_array($scopeId, ['topups', 'giftcards', 'gamekeys', 'manual'], true)) {
            throw new ApiException('این دسته فهرست جستجوپذیر ندارد.', 422, 'INVALID_CATALOG_SCOPE');
        }
        if ($remoteId === '') {
            throw new ApiException('شناسه مورد Fazer لازم است.', 422, 'INVALID_REMOTE_ID');
        }
        $row = $this->listedShop->findRow($spec, $remoteId);
        $this->ingestListedRow($spec, $row);
        $family = (string) $spec['family'];
        $product = Product::query()->where('fazercards_category_id', FazerCardsIds::productKey($family, $remoteId))->first();
        if (! $product) {
            throw new ApiException('این مورد در کاتالوگ Fazer قابل فروش نیست.', 404, 'NOT_FOUND');
        }
        $requirements = is_array($product->requirements) ? $product->requirements : [];
        $requirements['shopPinned'] = true;
        $product->requirements = $requirements;
        $product->status = 'active';
        if (! $product->isStorefrontExcluded()) {
            $product->visible = true;
        }
        $product->save();

        return $product;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function familySpecFor(string $phase): ?array
    {
        return $this->familySpec($phase);
    }

    private function revealSteamGift(int $appId): void
    {
        if ($appId <= 0) {
            return;
        }
        Product::query()
            ->where('fazercards_category_id', FazerCardsIds::productKey(FazerCardsIds::STEAM_GIFT, (string) $appId))
            ->where(fn ($query) => $query->whereNull('excluded_reason')->orWhere('excluded_reason', ''))
            ->update([
                'visible' => true,
                'status' => 'active',
            ]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function advanceListedFamily(array $state, array $spec): array
    {
        if (empty($state['listedQueueReady'])) {
            $all = $this->listedShop->allRows($spec);
            $items = $this->listedShop->worklistFromRows($spec, $all);
            $state['listedQueueReady'] = true;
            $state['pageItems'] = $items;
            $state['pageIndex'] = 0;
            $state['listCursor'] = '';
            $state['catalogTotal'] = count($items);
            $state['hasMore'] = $items !== [];
            $this->hideMissingRemoteProducts($spec, $all);
            $this->recordIncomplete($spec, $state);
            if ($items === []) {
                return $this->nextPhase($state);
            }
        }

        $items = is_array($state['pageItems'] ?? null) ? $state['pageItems'] : [];
        $index = (int) ($state['pageIndex'] ?? 0);
        $total = count($items);
        $state['catalogTotal'] = $total;
        if ($total < 1 || $index >= $total) {
            return $this->nextPhase($state);
        }

        $row = is_array($items[$index] ?? null) ? $items[$index] : [];
        $this->ingestListedRow($spec, $row);
        $remoteId = $this->stringId($row, $spec['idKeys']);
        $this->revealListed((string) ($spec['family'] ?? ''), $remoteId);
        $state['pageIndex'] = $index + 1;
        $state['processedItems'] = (int) ($state['processedItems'] ?? 0) + 1;
        $state['hasMore'] = $state['pageIndex'] < $total;
        $state['message'] = 'در حال همگام: '.$this->phaseLabel((string) ($state['phase'] ?? '')).' منتخب · '.$state['pageIndex'].' از '.$total;
        if (! $state['hasMore']) {
            return $this->nextPhase($state);
        }

        return $state;
    }

    private function revealListed(string $family, string $remoteId): void
    {
        $remoteId = trim($remoteId);
        if ($family === '' || $remoteId === '') {
            return;
        }
        $remoteKey = FazerCardsIds::productKey($family, $remoteId);
        Product::query()
            ->where(function ($query) use ($remoteKey): void {
                $query->where('fazercards_category_id', $remoteKey)
                    ->orWhereHas('supplierIdentities', function ($inner) use ($remoteKey): void {
                        $inner->where('supplier', ProductSupplierCatalog::FAZERCARDS)
                            ->where('remote_id', $remoteKey);
                    });
            })
            ->whereNull('substitute_product_id')
            ->whereNotIn('status', ['hidden', 'draft'])
            ->where(fn ($query) => $query->whereNull('excluded_reason')->orWhere('excluded_reason', ''))
            ->where(fn ($query) => $query->where('visible', false)->orWhere('status', '!=', 'active'))
            ->orderBy('id')
            ->each(function (Product $product): void {
                $product->visible = true;
                $product->status = 'active';
                $product->save();
                $this->restoredProducts++;
                Log::info('fazercards restored product', ['handle' => $product->handle]);
            });
    }

    /**
     * Hide Charjino products Fazer no longer lists. Shop titles/covers/descriptions stay.
     *
     * @param  array<string, mixed>  $spec
     * @param  list<array<string, mixed>>  $allRows
     */
    private function hideMissingRemoteProducts(array $spec, array $allRows): void
    {
        $family = (string) ($spec['family'] ?? '');
        if ($family === '' || ! in_array($family, [FazerCardsIds::TOPUP, FazerCardsIds::GIFTCARD, FazerCardsIds::GAMEKEY, FazerCardsIds::MANUAL], true)) {
            return;
        }
        $keep = [];
        foreach ($allRows as $row) {
            $id = $this->listedShop->remoteId($spec, is_array($row) ? $row : []);
            if ($id !== '') {
                $keep[FazerCardsIds::productKey($family, $id)] = true;
            }
        }
        if ($keep === []) {
            Log::warning('fazercards hideMissing skipped: empty remote catalog', ['family' => $family]);

            return;
        }
        $visibleQuery = Product::query()
            ->with('supplierIdentities')
            ->where('fulfillment_type', $family)
            ->where('visible', true)
            ->whereNull('substitute_product_id')
            ->where(function ($query): void {
                $query->where(function ($fazer): void {
                    $fazer->whereNotNull('fazercards_category_id')->where('fazercards_category_id', '!=', '');
                })->orWhereHas('supplierIdentities', function ($inner): void {
                    $inner->where('supplier', ProductSupplierCatalog::FAZERCARDS);
                });
            });
        $visibleTotal = (clone $visibleQuery)->count();
        $missing = [];
        $visibleQuery->orderBy('id')->each(function (Product $product) use ($keep, &$missing): void {
            $kept = false;
            foreach ($this->supplierCatalog()->remoteIds($product, ProductSupplierCatalog::FAZERCARDS) as $remote) {
                if (isset($keep[$remote])) {
                    $kept = true;
                    break;
                }
            }
            if (! $kept) {
                $missing[] = $product;
            }
        });
        $wouldHide = count($missing);
        $maxPercent = (float) config('charjino.supplier.fazercards_hide_missing_max_percent', 5);
        if ($visibleTotal > 0 && ($wouldHide / $visibleTotal) * 100 > $maxPercent) {
            Log::warning('fazercards hideMissing aborted: family hide cap', [
                'family' => $family,
                'visible' => $visibleTotal,
                'wouldHide' => $wouldHide,
                'maxPercent' => $maxPercent,
            ]);
            $this->syncIncomplete[] = [
                'family' => $family,
                'message' => 'مخفی‌کردن انبوه '.$family.' متوقف شد ('.$wouldHide.' از '.$visibleTotal.'). احتمال خطای API فیزر.',
            ];

            return;
        }
        foreach ($missing as $product) {
            $product->visible = false;
            $product->save();
            $this->hiddenProducts++;
            Log::info('fazercards hid missing product', [
                'handle' => $product->handle,
                'family' => $family,
                'fazercards_category_id' => $product->fazercards_category_id,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $row
     */
    private function ingestListedRow(array $spec, array $row): bool
    {
        $remoteId = $this->stringId($row, $spec['idKeys']);
        $name = $this->displayName($row, $remoteId);
        if ($remoteId === '' || CatalogExclusion::shouldSkipIngest($name, $row)) {
            SteamGiftBlocklist::excludeNamed($name, 'steam-gift-'.$remoteId, $remoteId);

            return false;
        }
        if (($spec['family'] ?? '') === FazerCardsIds::STEAM_GIFT && (int) $remoteId <= 0) {
            return false;
        }

        $offers = $this->listFromRow($row, $spec['nested']);
        $offersPath = (string) ($spec['offersPath'] ?? '');
        $detail = [];
        if ($offers === [] && ($spec['family'] ?? '') === FazerCardsIds::MANUAL) {
            $payload = $this->client->fetch('manual-services/'.$remoteId.'/offers', 'GET', ['include_ui' => '1']);
            $detail = FazerCardsPayload::unwrap($payload['data']);
            $offers = FazerCardsPayload::listFrom($payload['data'], ['items', 'offers']);
        } elseif ($offers === [] && ($spec['family'] ?? '') === FazerCardsIds::STEAM_GIFT) {
            try {
                $payload = $this->client->fetch('steam-gifts/games/'.$remoteId);
            } catch (ApiException $e) {
                if (in_array($e->errorCode, ['SUPPLIER_UNREACHABLE', 'SUPPLIER_UNAVAILABLE', 'RATE_LIMIT_EXCEEDED'], true)) {
                    throw $e;
                }

                return false;
            }
            $detail = FazerCardsPayload::unwrap($payload['data']);
            $offers = FazerCardsSteamGiftQuotes::regionOffers($payload['data']);
            $giftName = trim((string) ($detail['name'] ?? $row['name'] ?? ''));
            if ($giftName !== '') {
                $name = $giftName;
            }
            if (SteamGiftBlocklist::matches($name.' '.$remoteId)) {
                SteamGiftBlocklist::excludeNamed($name, 'steam-gift-'.$remoteId, $remoteId);

                return false;
            }
            $cover = FazerCardsMedia::absolute($this->stringId($detail, ['imageurl', 'image_url']));
            if ($cover === '' || FazerCardsMedia::isLegacySteamHeader($cover)) {
                $cached = Cache::get(SteamCoverResolver::cacheKey((int) $remoteId));
                $cover = is_string($cached) && $cached !== '' && FazerCardsMedia::isHashedSteamHeader($cached)
                    ? $cached
                    : FazerCardsMedia::steamHeader($remoteId);
            }
            $row['imageurl'] = $cover;
            $row['appid'] = (int) $remoteId;
        } elseif ($offersPath !== '') {
            $queryKey = (string) ($spec['offersQuery'] ?? '');
            $query = $queryKey !== '' ? [$queryKey => $remoteId] : [];
            if (is_array($spec['offersExtra'] ?? null)) {
                $query = array_merge($query, $spec['offersExtra']);
            }
            $payload = $this->client->fetch($offersPath, 'GET', $query);
            $detail = FazerCardsPayload::unwrap($payload['data']);
            $fetched = FazerCardsPayload::listFrom($payload['data'], $spec['nested']);
            if ($offers === []) {
                $offers = $fetched;
            }
        }
        if ($offers === [] && ($spec['selfIfPriced'] ?? false) && $this->usd($row) !== '0') {
            $offers = [$row];
        }
        $specSubtitle = (string) ($spec['subtitle'] ?? '');
        $subtitle = $specSubtitle;
        $inputs = is_array($spec['inputs'] ?? null) ? $spec['inputs'] : [];
        if (($spec['family'] ?? '') === FazerCardsIds::GAMEKEY) {
            $offers = $this->filterStockedOffers($offers, ['key_id', 'offer_id', 'id']);
            $region = $this->stringId($row, ['region']);
            if ($region !== '') {
                foreach ($offers as $index => $offer) {
                    if ($this->stringId($offer, ['region', 'country']) === '') {
                        $offers[$index]['region'] = $region;
                    }
                }
            }
        }
        if (($spec['family'] ?? '') === FazerCardsIds::GIFTCARD) {
            $offers = $this->filterStockedOffers($offers, ['card_id', 'offer_id', 'id']);
        }
        if (
            $detail !== []
            && in_array($spec['family'] ?? '', [FazerCardsIds::TOPUP, FazerCardsIds::GAMEKEY, FazerCardsIds::GIFTCARD], true)
        ) {
            $row = array_merge($row, array_filter([
                'name' => $detail['GameName'] ?? $detail['name'] ?? null,
                'note' => $detail['note'] ?? null,
                'imageurl' => $detail['imageurl'] ?? $detail['image_url'] ?? null,
                'appid' => $detail['appid'] ?? null,
                'region' => $detail['region'] ?? null,
                'platform' => $detail['platform'] ?? null,
                'region_restriction' => $detail['region_restriction'] ?? $row['region_restriction'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));
            $name = $this->displayName($row, $remoteId);
            $note = $this->stringId($row, ['note']);
            $subtitle = $this->shopSubtitle($note, $specSubtitle);
            if (($spec['family'] ?? '') === FazerCardsIds::TOPUP) {
                $inputs = $this->topupInputsFromDetail($detail);
                if ($this->detailHasCredentialFields($detail)) {
                    $row['credential_fields'] = true;
                }
            }
        }
        if ($detail !== [] && ($spec['family'] ?? '') === FazerCardsIds::MANUAL) {
            $category = is_array($detail['category'] ?? null) ? $detail['category'] : [];
            $row = array_merge($row, array_filter([
                'name' => $category['name'] ?? $detail['name'] ?? null,
                'info' => $detail['info'] ?? null,
                'imageurl' => $detail['imageurl'] ?? $detail['image_url'] ?? null,
                'kind' => $category['kind'] ?? $detail['kind'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));
            $name = $this->displayName($row, $remoteId);
            $info = $this->stringId($row, ['info']);
            $subtitle = $this->shopSubtitle($info, $specSubtitle);
            $fromFields = $this->manualInputsFromDetail($detail);
            if ($fromFields !== []) {
                $inputs = $fromFields;
            }
        }

        return $this->ingestCategory(
            $this->namedGroup(
                (string) $spec['slug'],
                (string) $spec['title'],
                (int) $spec['sort'],
                (string) ($spec['titleEn'] ?? ''),
            ),
            (string) $spec['family'],
            $remoteId,
            $name,
            $row,
            $offers,
            $inputs,
            $subtitle,
            fn (array $offer) => $this->stringId($offer, $spec['offerIdKeys']),
        );
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function nextPhase(array $state): array
    {
        $current = (string) ($state['phase'] ?? '');
        $phases = is_array($state['phases'] ?? null) && $state['phases'] !== []
            ? array_values(array_filter($state['phases'], fn ($phase) => is_string($phase) && $phase !== ''))
            : self::PHASES;
        $index = array_search($current, $phases, true);
        $next = is_int($index) ? ($phases[$index + 1] ?? 'finalize') : 'finalize';
        $state['phase'] = $next;
        $state['phaseLabel'] = $this->phaseLabel($next);
        $state['listCursor'] = '';
        $state['pageItems'] = [];
        $state['pageIndex'] = 0;
        $state['hasMore'] = false;
        unset($state['steamQueueReady'], $state['listedQueueReady']);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function finalize(array $state): array
    {
        $hiddenSkus = $this->hideMissingSkus($this->scopeFamilies($state)) + $this->hideUnmappedSeedSkus($this->scopeFamilies($state));
        $this->pricing->repriceAllSkus($this->scopeFamilies($state));
        $finished = now();
        $started = isset($state['startedAt']) ? Carbon::parse((string) $state['startedAt']) : now();
        $this->dispatchSearchCatchUp($started);
        $ok = $this->syncIncomplete === [];
        Log::info('fazercards sync finished', [
            'ok' => $ok,
            'scope' => $state['scope'] ?? null,
            'hiddenProducts' => $this->hiddenProducts,
            'restoredProducts' => $this->restoredProducts,
            'hiddenSkus' => $hiddenSkus,
            'products' => $this->productsUpserted,
            'skus' => $this->skusUpserted,
        ]);

        return [
            'status' => 'completed',
            'scope' => $state['scope'] ?? FazerCardsCatalogScopes::ALL,
            'scopeLabel' => $state['scopeLabel'] ?? 'همهٔ دسته‌ها',
            'families' => $state['families'] ?? null,
            'phase' => 'done',
            'phaseLabel' => 'تمام',
            'startedAt' => $state['startedAt'] ?? $started->toIso8601String(),
            'finishedAt' => $finished->toIso8601String(),
            'durationMs' => (int) abs($started->diffInMilliseconds($finished)),
            'account' => $state['account'] ?? null,
            'groups' => CatalogGroup::query()
                ->whereIn('slug', ['game-topup', 'gift-card', 'game-keys', 'steam-wallet', 'steam-gifts', 'telegram', 'manual-services'])
                ->count(),
            'products' => $this->productsUpserted,
            'skus' => $this->skusUpserted,
            'hiddenSkus' => $hiddenSkus,
            'hiddenProducts' => $this->hiddenProducts,
            'restoredProducts' => $this->restoredProducts,
            'processedItems' => (int) ($state['processedItems'] ?? 0),
            'pricing' => $this->pricing->snapshot(),
            'syncIncomplete' => $this->syncIncomplete,
            'message' => $this->syncIncomplete !== []
                ? 'کاتالوگ «'.((string) ($state['scopeLabel'] ?? 'FazerCards')).'» همگام شد. '.($this->syncIncomplete[0]['message'] ?? 'همگام‌سازی ناقص')
                : 'کاتالوگ «'.((string) ($state['scopeLabel'] ?? 'FazerCards')).'» همگام شد.',
            'seen' => [],
            'touched' => [],
            'pageItems' => [],
        ];
    }

    private function dispatchSearchCatchUp(CarbonInterface $started): void
    {
        SmartSearchSyncService::$bulkQuiet = false;
        if (! SmartSearchSyncService::writesAreEnabled()) {
            return;
        }
        $ids = Product::query()->where('updated_at', '>=', $started->copy()->subMinute())->pluck('id')
            ->merge(Sku::query()->where('updated_at', '>=', $started->copy()->subMinute())->pluck('product_id'))
            ->unique()
            ->filter()
            ->values()
            ->all();
        foreach (array_chunk(array_map('intval', $ids), 80) as $chunk) {
            SyncProductSearchDocuments::dispatch($chunk);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function familySpec(string $phase): ?array
    {
        return match ($phase) {
            'topups' => [
                'slug' => 'game-topup',
                'title' => 'شارژ بازی',
                'titleEn' => 'Game top-up',
                'sort' => 10,
                'path' => 'topups',
                'listKeys' => ['items', 'categories'],
                'listQuery' => $this->listedListQuery(['include_ui' => '1']),
                'family' => FazerCardsIds::TOPUP,
                'idKeys' => ['category_id', 'id'],
                'nested' => ['offers', 'items'],
                'offersPath' => 'topups/offers',
                'offersQuery' => 'category_id',
                'offersExtra' => ['include_ui' => '1'],
                'inputs' => [],
                'subtitle' => 'شارژ با شناسه بازیکن',
                'offerIdKeys' => ['offer_id', 'id'],
            ],
            'giftcards' => [
                'slug' => 'gift-card',
                'title' => 'گیفت کارت',
                'titleEn' => 'Gift cards',
                'sort' => 20,
                'path' => 'giftcards',
                'listKeys' => ['items', 'categories'],
                'listQuery' => $this->listedListQuery(['include_ui' => '1']),
                'family' => FazerCardsIds::GIFTCARD,
                'idKeys' => ['category_id', 'id'],
                'nested' => ['offers', 'cards', 'items'],
                'offersPath' => 'giftcards/cards',
                'offersQuery' => 'category_id',
                'offersExtra' => ['include_ui' => '1'],
                'inputs' => [],
                'subtitle' => 'کد گیفت بعد از پرداخت',
                'offerIdKeys' => ['card_id', 'offer_id', 'id'],
            ],
            'gamekeys' => [
                'slug' => 'game-keys',
                'title' => 'کلید بازی',
                'titleEn' => 'Game keys',
                'sort' => 30,
                'path' => 'gamekeys',
                'listKeys' => ['items', 'games'],
                'listQuery' => $this->listedListQuery(['include_ui' => '1']),
                'family' => FazerCardsIds::GAMEKEY,
                'idKeys' => ['game_id', 'id'],
                'nested' => ['keys', 'offers', 'items'],
                'offersPath' => 'gamekeys/keys',
                'offersQuery' => 'game_id',
                'offersExtra' => ['include_ui' => '1'],
                'inputs' => [],
                'subtitle' => 'کلید بازی بعد از پرداخت',
                'offerIdKeys' => ['key_id', 'offer_id', 'id'],
            ],
            'steam-gifts' => [
                'slug' => 'steam-gifts',
                'title' => 'گیفت استیم',
                'titleEn' => 'Steam gifts',
                'sort' => 55,
                'path' => 'steam-gifts/games',
                'listKeys' => ['games', 'items'],
                'listQuery' => ['_omit_limit' => true],
                'family' => FazerCardsIds::STEAM_GIFT,
                'idKeys' => ['appid', 'app_id', 'id'],
                'nested' => ['offers'],
                'offersPath' => '',
                'inputs' => [],
                'subtitle' => 'لینک دعوت استیم؛ رمز گرفته نمی‌شود',
                'offerIdKeys' => ['offer_id', 'sub_id'],
            ],
            'manual' => [
                'slug' => 'manual-services',
                'title' => 'سرویس دستی',
                'titleEn' => 'Manual services',
                'sort' => 60,
                'path' => 'manual-services',
                'listKeys' => ['items', 'services'],
                'listQuery' => ['include_ui' => '1', '_omit_limit' => true],
                'family' => FazerCardsIds::MANUAL,
                'idKeys' => ['id', 'manual_service_id', 'service_id'],
                'nested' => ['items', 'offers'],
                'offersPath' => '',
                'inputs' => [],
                'subtitle' => 'سرویس با پیگیری اپراتور',
                'offerIdKeys' => ['id', 'product_id', 'offer_id'],
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $account
     * @return array{email: string, wallet: string, currency: string, plan: string}
     */
    private function publicAccount(array $account): array
    {
        return [
            'email' => (string) ($account['email'] ?? ''),
            'wallet' => (string) ($account['wallet'] ?? '0'),
            'currency' => 'USD',
            'plan' => (string) ($account['plan'] ?? ''),
        ];
    }

    private function syncTelegram(): void
    {
        $group = $this->namedGroup('telegram', 'تلگرام', 40, 'Telegram');
        $starsPayload = $this->client->fetch('telegram/stars');
        $stars = FazerCardsTelegramQuotes::starOffers($starsPayload['data']);
        $this->ingestCategory(
            $group,
            FazerCardsIds::TELEGRAM_STARS,
            'stars',
            'ستاره تلگرام',
            ['name' => 'Telegram Stars', 'imageurl' => '/image/catalog/telegram.svg'],
            $stars,
            [],
            'خرید ستاره با نام کاربری تلگرام',
            fn (array $offer) => $this->stringId($offer, ['quantity', 'stars', 'offer_id', 'id']),
        );

        $premiumPayload = $this->client->fetch('telegram/premium');
        $premium = FazerCardsTelegramQuotes::premiumOffers($premiumPayload['data']);
        $this->ingestCategory(
            $group,
            FazerCardsIds::TELEGRAM_PREMIUM,
            'premium',
            'پرمیوم تلگرام',
            ['name' => 'Telegram Premium', 'imageurl' => '/image/catalog/telegram.svg'],
            $premium,
            [],
            'خرید پرمیوم با نام کاربری تلگرام',
            fn (array $offer) => $this->stringId($offer, ['months', 'offer_id', 'id']),
        );
    }

    private function syncSteamTopup(): void
    {
        $group = $this->namedGroup('steam-wallet', 'شارژ استیم', 50, 'Steam wallet');
        $payload = $this->client->fetch('steam-topup/rates');
        $this->ingestCategory(
            $group,
            FazerCardsIds::STEAM_TOPUP,
            'wallet',
            'شارژ کیف پول استیم',
            ['name' => 'Steam Wallet', 'imageurl' => '/image/catalog/steam.svg'],
            FazerCardsSteamTopupQuotes::offers($payload['data']),
            [],
            'فقط لاگین استیم؛ رمز گرفته نمی‌شود',
            function (array $offer): string {
                $id = $this->stringId($offer, ['offer_id']);

                return $id !== '' ? $id : FazerCardsSteamTopupQuotes::FREE_OFFER_ID;
            },
        );
        Sku::query()
            ->where('fulfillment_type', FazerCardsIds::STEAM_TOPUP)
            ->where('fazercards_offer_id', '!=', FazerCardsSteamTopupQuotes::FREE_OFFER_ID)
            ->update([
                'status' => 'hidden',
                'visible' => false,
            ]);
    }

    /**
     * FunPay در OpenAPI فقط login و me است؛ فهرست کالا برای فروشگاه ندارد.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function syncFunpay(array $state): array
    {
        try {
            $payload = $this->client->funpayMe();
            $data = FazerCardsPayload::unwrap($payload['data']);
            $addon = is_array($data['addon'] ?? null) ? $data['addon'] : [];
            $active = filter_var($addon['active'] ?? false, FILTER_VALIDATE_BOOLEAN)
                || strtolower(trim((string) ($addon['status'] ?? ''))) === 'active';
            $state['message'] = $active
                ? 'پنل FunPay وصل است. در API Fazer برای FunPay کاتالوگ کالا نیست (فقط login و me).'
                : 'ادون FunPay روی این حساب فعال نیست. کالای FunPay وارد فروشگاه نمی‌شود.';
        } catch (ApiException $e) {
            if ($e->errorCode === 'FUNPAY_ADDON_REQUIRED' || $e->status === 403) {
                $state['message'] = 'ادون FunPay روی این حساب فعال نیست. کالای FunPay وارد فروشگاه نمی‌شود.';
            } else {
                throw $e;
            }
        }
        $state['processedItems'] = (int) ($state['processedItems'] ?? 0) + 1;

        return $this->nextPhase($state);
    }

    private function forgetRun(): void
    {
        Cache::forget(FazerCardsSyncStatus::CACHE_KEY);
        Cache::forget(FazerCardsSyncStatus::STATE_KEY);
    }

    private function forgetRunIfOwned(): void
    {
        $state = FazerCardsSyncStatus::state();
        if (($state['status'] ?? '') === 'running') {
            $owner = (string) ($state['lockOwner'] ?? '');
            $mine = $this->heavyLock?->owner() ?? '';
            if ($owner !== '' && $owner !== $mine) {
                return;
            }
        }
        $this->forgetRun();
    }

    private function normalizeSteamMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return in_array($mode, ['visible', 'weekly', 'full'], true) ? $mode : 'full';
    }

    private function pastWeeklyStopHour(): bool
    {
        $hour = (int) config('charjino.supplier.fazercards_weekly_stop_hour', 8);

        return now()->timezone('Asia/Tehran')->hour >= $hour;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function listedListQuery(array $extra = []): array
    {
        $query = $extra;
        $limit = (int) config('charjino.supplier.fazercards_list_page_limit', 0);
        if ($limit >= 1) {
            $query['limit'] = $limit;
        } else {
            $query['_omit_limit'] = true;
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function loadCounters(array $state): void
    {
        $this->seen = [];
        $this->syncIncomplete = [];
        foreach (is_array($state['syncIncomplete'] ?? null) ? $state['syncIncomplete'] : [] as $row) {
            if (is_array($row)) {
                $this->syncIncomplete[] = $row;
            }
        }
        foreach (is_array($state['seen'] ?? null) ? $state['seen'] : [] as $key => $value) {
            if (is_string($key) && $key !== '') {
                $this->seen[$key] = true;
            } elseif (is_string($value) && $value !== '') {
                $this->seen[$value] = true;
            }
        }
        $this->productsUpserted = (int) ($state['products'] ?? 0);
        $this->skusUpserted = (int) ($state['skus'] ?? 0);
        $this->touchedCategoryKeys = [];
        foreach (is_array($state['touched'] ?? null) ? $state['touched'] : [] as $key => $value) {
            if (is_string($key) && $key !== '') {
                $this->touchedCategoryKeys[$key] = true;
            } elseif (is_string($value) && $value !== '') {
                $this->touchedCategoryKeys[$value] = true;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function storeCounters(array $state): array
    {
        $state['seen'] = $this->seen;
        $state['touched'] = array_keys($this->touchedCategoryKeys);
        $state['syncIncomplete'] = $this->syncIncomplete;
        $state['products'] = $this->productsUpserted;
        $state['skus'] = $this->skusUpserted;

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function withHeartbeat(array $state): array
    {
        $state['heartbeatAt'] = now()->toIso8601String();
        if ($this->heavyLock !== null) {
            $state['lockOwner'] = $this->heavyLock->owner();
        }

        return $state;
    }

    private function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'topups' => 'شارژ بازی',
            'giftcards' => 'گیفت کارت',
            'gamekeys' => 'کلید بازی',
            'telegram' => 'تلگرام (ستاره)',
            'steam-topup' => 'شارژ استیم (CIS)',
            'steam-gifts' => 'گیفت استیم',
            'funpay' => 'FunPay',
            'manual' => 'سرویس دستی',
            'finalize' => 'جمع‌بندی',
            'done' => 'تمام',
            default => $phase,
        };
    }

    /**
     * @param  array<string, mixed>  $remote
     * @param  list<array<string, mixed>>  $offers
     * @param  list<string|array<string, mixed>>  $inputs
     * @param  callable(array<string, mixed>): string  $offerId
     */
    private function ingestCategory(
        CatalogGroup $group,
        string $family,
        string $remoteId,
        string $name,
        array $remote,
        array $offers,
        array $inputs,
        string $subtitle,
        callable $offerId,
    ): bool {
        $activeOffers = [];
        foreach ($offers as $offer) {
            $oid = trim($offerId($offer));
            $offerName = $this->displayName($offer, $oid);
            if ($oid === '' || $this->forbidden($offerName, $offer)) {
                continue;
            }
            $usd = $this->usd($offer);
            if ((float) $usd <= 0) {
                continue;
            }
            if ($family === FazerCardsIds::GAMEKEY && ! $this->stockedCatalogOffer($offer, ['key_id', 'offer_id', 'id'])) {
                continue;
            }
            if ($family === FazerCardsIds::GIFTCARD && ! $this->stockedCatalogOffer($offer, ['card_id', 'offer_id', 'id'])) {
                continue;
            }
            $activeOffers[] = [$offer, $oid, $offerName, $usd];
        }
        if ($activeOffers === []) {
            return false;
        }

        $productId = FazerCardsContentionRetry::run(
            function () use ($group, $family, $remoteId, $name, $remote, $inputs, $subtitle, $activeOffers): int {
                return (int) DB::transaction(function () use ($group, $family, $remoteId, $name, $remote, $inputs, $subtitle, $activeOffers): int {
                    $product = $this->upsertProduct($group, $family, $remoteId, $name, $remote, $inputs, $subtitle);
                    $this->touchedCategoryKeys[FazerCardsIds::productKey($family, $remoteId)] = true;
                    $this->maybeSetGroupCover($group, (string) $product->thumbnail);
                    $sort = 10;
                    foreach ($activeOffers as [$offer, $oid, $offerName, $usd]) {
                        $this->upsertSku($product, $family, $remoteId, $oid, $offerName, $usd, $sort, is_array($offer) ? $offer : []);
                        $sort += 10;
                    }

                    return (int) $product->id;
                });
            },
            function () use ($family, $remoteId): void {
                $product = Product::query()
                    ->where('fazercards_category_id', FazerCardsIds::productKey($family, $remoteId))
                    ->first();
                if ($product) {
                    FazerCardsSkuSyncFailure::markProduct((int) $product->id);
                }
                $this->rejectedProducts++;
                FazerCardsSyncMetrics::rejected();
                Log::channel('fazercards')->warning('fazercards upsert skipped after contention', [
                    'family' => $family,
                    'remote_id' => $remoteId,
                    'product_id' => $product?->id,
                ]);
            },
        );
        if (! is_int($productId) || $productId < 1) {
            return false;
        }
        FazerCardsSkuSyncFailure::clearCount($productId);

        return true;
    }

    /**
     * @param  array<string, mixed>  $remote
     * @param  list<string|array<string, mixed>>  $inputs
     */
    private function upsertProduct(
        CatalogGroup $group,
        string $family,
        string $remoteId,
        string $name,
        array $remote,
        array $inputs,
        string $subtitle,
    ): Product {
        $key = FazerCardsIds::productKey($family, $remoteId);
        $hint = $family === FazerCardsIds::TOPUP ? FazerCardsSeeds::storefrontHint($remoteId) : null;
        $incomingRegion = $this->stringId($remote, ['region']);
        $handle = (string) ($hint['handle'] ?? $this->candidateHandle($family, $remoteId));
        $existing = Product::query()->where('fazercards_category_id', $key)->first()
            ?? $this->supplierCatalog()->findByRemote(ProductSupplierCatalog::FAZERCARDS, $key)
            ?? Product::query()->where('handle', $handle)->first()
            ?? $this->supplierCatalog()->findLiveOrAnyByKey($handle, $family, $incomingRegion);
        if ($existing) {
            $existing = $this->supplierCatalog()->resolveCanonical($existing);
            $handle = (string) $existing->handle;
        } else {
            $handle = (string) ($hint['handle'] ?? $this->makeHandle($family, $remoteId));
        }

        $titleFa = $this->keepFilled(
            $existing?->title,
            (string) ($hint['titleFa'] ?? $name),
        );
        $nameEn = $this->keepFilled(
            $existing?->name_en,
            (string) ($remote['name'] ?? $remote['title'] ?? $name),
        );
        $incomingCover = $this->imageOf($remote, (string) ($hint['thumbnail'] ?? '/image/catalog/pubg-mobile.svg'));
        $cover = $this->keepShopCover($existing, $incomingCover);
        $requirements = is_array($existing?->requirements) ? $existing->requirements : [];
        $appid = $this->stringId($remote, ['appid', 'app_id']);
        if ($appid !== '' && (int) $appid > 0) {
            $requirements['appid'] = (int) $appid;
        }
        if (isset($remote['listedRank']) && is_numeric($remote['listedRank'])) {
            $requirements['listedRank'] = (int) $remote['listedRank'];
        }
        $this->maybeRegionAvailability($family, $remoteId, $remote, $requirements);
        $incomingPlatform = $this->stringId($remote, ['platform']);
        $incomingRegion = $this->stringId($remote, ['region']);
        if ($family === FazerCardsIds::GAMEKEY) {
            $incomingPlatform = GameKeyPlatform::fromFazer($remote)
                ?? GameKeyPlatform::canonical($incomingPlatform)
                ?? GameKeyPlatform::canonical((string) $existing?->platform)
                ?? '';
        } elseif ($family === FazerCardsIds::GIFTCARD) {
            $parsed = GiftCardBrand::fromRemote($remoteId, $name);
            $incomingPlatform = $this->keepFilled($existing?->platform, $parsed['platform'] !== '' ? $parsed['platform'] : $incomingPlatform);
            $incomingRegion = $this->keepFilled(
                $existing?->region,
                $incomingRegion !== '' ? $incomingRegion : $parsed['region'],
            );
        } elseif (in_array($family, [FazerCardsIds::STEAM_GIFT, FazerCardsIds::STEAM_TOPUP], true)) {
            $incomingPlatform = $this->keepFilled($existing?->platform, $incomingPlatform !== '' ? $incomingPlatform : 'steam');
        }
        $resolvedInputs = $this->productInputs($family, $existing, $inputs);
        $excluded = CatalogExclusion::reason(
            $family,
            $titleFa.' '.$nameEn.' '.$handle.' '.$key,
            $resolvedInputs,
            $remote,
        );
        $existingSchema = is_array($existing?->purchase_schema) ? $existing->purchase_schema : [];
        $schema = PurchaseSchema::build($family, $resolvedInputs, [
            'noteEn' => $this->stringId($remote, ['note', 'info']),
            'name' => $titleFa.' '.$nameEn,
            'regionNote' => (string) ($existingSchema['regionNote'] ?? ''),
            'validateCategoryId' => $remoteId,
            'validateSupportedIds' => $this->validateSupportedIds(),
        ]);
        $product = Product::query()->updateOrCreate(
            ['handle' => $existing?->handle ?: $handle],
            [
                'catalog_group_id' => $existing?->catalog_group_id ?: $group->id,
                'fazercards_category_id' => filled($existing?->fazercards_category_id)
                    ? $existing->fazercards_category_id
                    : $key,
                'code' => $existing?->code ?: $handle,
                'title' => $this->clipColumn($titleFa, 255),
                'name_en' => $this->clipColumn($nameEn, 255),
                'publisher' => $this->clipColumn($this->keepFilled($existing?->publisher, (string) ($hint['publisher'] ?? '')), 255),
                'category_name' => $this->clipColumn($existing?->category_name ?: $group->title, 255),
                'subtitle' => $this->shopSubtitle($this->keepFilled($existing?->subtitle, $subtitle), $subtitle),
                'description' => $this->shopDescription($existing?->description, $this->stringId($remote, ['note', 'info'])),
                'thumbnail' => $this->clipColumn($cover, 255),
                'image_url' => $this->clipColumn($cover, 255),
                'fulfillment_type' => $family,
                'platform' => $incomingPlatform !== '' ? $incomingPlatform : $existing?->platform,
                'region' => $incomingRegion !== ''
                    ? CatalogAttr::publicRegion($incomingRegion)
                    : $existing?->region,
                'requirements' => $requirements !== [] ? $requirements : $existing?->requirements,
                'inputs' => $resolvedInputs,
                'purchase_schema' => $schema,
                'excluded_reason' => $excluded,
                'synced_at' => now(),
                'visible' => $excluded !== null ? false : ($existing ? (bool) $existing->getAttribute('visible') : true),
                'status' => $existing?->status ?: 'active',
                ...($existing === null && $family === FazerCardsIds::STEAM_GIFT ? ['indexable' => false] : []),
            ]
        );
        $this->productsUpserted++;
        $this->supplierCatalog()->remember($product, ProductSupplierCatalog::FAZERCARDS, $key);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    private function upsertSku(
        Product $product,
        string $family,
        string $remoteId,
        string $offerId,
        string $offerName,
        string $usd,
        int $sort,
        array $offer,
    ): Sku {
        $categoryKey = FazerCardsIds::skuCategory($family, $remoteId);
        if ($family !== FazerCardsIds::STEAM_GIFT) {
            $this->seen[FazerCardsIds::seenKey($family, $categoryKey, $offerId)] = true;
        }
        $code = in_array($family, [FazerCardsIds::TOPUP, FazerCardsIds::GAMEKEY, FazerCardsIds::GIFTCARD, FazerCardsIds::MANUAL, FazerCardsIds::STEAM_GIFT], true)
            ? mb_substr($family.'-'.$remoteId.'-'.str_replace('|', '-', $offerId), 0, 80)
            : mb_substr($family.'-'.$offerId, 0, 80);
        $existing = Sku::query()
            ->where('fazercards_category_id', $categoryKey)
            ->where('fazercards_offer_id', $offerId)
            ->first()
            ?? Sku::query()->where('product_id', $product->id)->where('code', $code)->first();

        $rawRegion = $this->stringId($offer, ['region', 'country']);
        $stock = array_key_exists('stock', $offer) ? max(0, (int) $offer['stock']) : null;
        if (in_array($family, [
            FazerCardsIds::TOPUP,
            FazerCardsIds::STEAM_TOPUP,
            FazerCardsIds::STEAM_GIFT,
            FazerCardsIds::TELEGRAM_STARS,
            FazerCardsIds::TELEGRAM_PREMIUM,
        ], true)) {
            $minQty = 1;
            $maxQty = 1;
            $stock = null;
        } else {
            $minQty = CatalogAttr::minOrderQty((int) ($offer['min_order_quantity'] ?? $offer['min_qty'] ?? 1));
            $offerMax = (int) ($offer['max_order_quantity'] ?? $offer['max_qty'] ?? 0);
            $maxQty = CatalogAttr::maxOrderQty($offerMax > 0 ? $offerMax : null, $minQty);
            if ($stock !== null) {
                $maxQty = max($minQty, min($maxQty, $stock));
            }
        }

        $attrs = [
            'product_id' => $product->id,
            'code' => $existing?->code ?: $code,
            'name' => $this->clipColumn($existing?->name ?: $offerName, 255),
            'name_en' => $this->clipColumn($this->keepFilled($existing?->name_en, $offerName), 255),
            'fazercards_category_id' => $categoryKey,
            'fazercards_offer_id' => mb_substr($offerId, 0, 120),
            'fulfillment_type' => $family,
            'cost_usd' => $usd,
            'live_cost_usd' => $usd,
            'price' => ($existing && $existing->price_manual)
                ? $existing->price
                : $this->pricing->sellTomanFor($existing, $usd, $product),
            'region' => $rawRegion !== ''
                ? CatalogAttr::publicRegion($rawRegion)
                : ($existing?->region ?: ($family === FazerCardsIds::GIFTCARD ? $product->region : null)),
            'min_order_quantity' => $minQty,
            'max_order_quantity' => $maxQty,
            'stock' => $stock,
            'synced_at' => now(),
            'status' => 'active',
            'visible' => true,
            'sort_order' => $existing?->sort_order ?? $sort,
        ];

        if ($existing) {
            $existing->fill($attrs);
            $existing->save();
            $this->skusUpserted++;

            return $existing;
        }

        $sku = Sku::query()->create($attrs);
        $this->skusUpserted++;

        return $sku;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<string>|null
     */
    private function scopeFamilies(array $state): ?array
    {
        $families = $state['families'] ?? null;
        if (! is_array($families) || $families === []) {
            return null;
        }

        $out = [];
        foreach ($families as $family) {
            if (is_string($family) && $family !== '') {
                $out[] = $family;
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  list<string>|null  $families
     */
    private function hideMissingSkus(?array $families = null): int
    {
        $hidden = 0;
        $query = Sku::query()
            ->whereNotNull('fazercards_category_id')
            ->where('fazercards_category_id', '!=', '')
            ->whereNotNull('fazercards_offer_id')
            ->where('fazercards_offer_id', '!=', '');
        if ($families !== null) {
            $query->whereIn('fulfillment_type', $families);
        }
        $query
            ->orderBy('id')
            ->chunkById(200, function ($skus) use (&$hidden): void {
                foreach ($skus as $sku) {
                    $family = FazerCardsIds::familyOf($sku->fulfillment_type, $sku->fazercards_category_id);
                    if ($family === FazerCardsIds::STEAM_GIFT || $family === FazerCardsIds::FUNPAY) {
                        continue;
                    }
                    if (in_array($family, [FazerCardsIds::TOPUP, FazerCardsIds::GIFTCARD, FazerCardsIds::GAMEKEY, FazerCardsIds::MANUAL], true)
                        && ! isset($this->touchedCategoryKeys[(string) $sku->fazercards_category_id])) {
                        continue;
                    }
                    $key = FazerCardsIds::seenKey(
                        $family,
                        (string) $sku->fazercards_category_id,
                        (string) $sku->fazercards_offer_id,
                    );
                    if (isset($this->seen[$key])) {
                        continue;
                    }
                    $sku->status = 'hidden';
                    $sku->visible = false;
                    $sku->save();
                    $hidden++;
                }
            });

        return $hidden;
    }

    /**
     * @param  list<string>|null  $families
     */
    private function hideUnmappedSeedSkus(?array $families = null): int
    {
        return (int) Sku::query()
            ->whereHas('product', function ($query) use ($families) {
                $query->whereNotNull('fazercards_category_id')->where('fazercards_category_id', '!=', '');
                if ($families !== null) {
                    $query->whereIn('fulfillment_type', $families);
                }
            })
            ->where(function ($query) {
                $query->whereNull('fazercards_offer_id')->orWhere('fazercards_offer_id', '');
            })
            ->update([
                'status' => 'hidden',
                'visible' => false,
            ]);
    }

    /**
     * GET /topups/offers فیلدهای فرم را جدا از لیست دسته برمی‌گرداند.
     *
     * @param  array<string, mixed>  $detail
     * @return list<array<string, mixed>>
     */
    private function topupInputsFromDetail(array $detail): array
    {
        $fields = $detail['fields'] ?? [];
        if (! is_array($fields)) {
            return [];
        }
        $out = [];
        foreach ($fields as $row) {
            if (! is_array($row)) {
                continue;
            }
            $enriched = TopupFieldCatalog::enrich($row);
            if ($enriched !== null) {
                $out[] = $enriched;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function detailHasCredentialFields(array $detail): bool
    {
        $fields = $detail['fields'] ?? [];
        if (! is_array($fields)) {
            return false;
        }
        foreach ($fields as $row) {
            if (CatalogExclusion::isCredentialKey(CatalogExclusion::fieldKey($row))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $remote
     * @param  array<string, mixed>  $requirements
     */
    private function maybeRegionAvailability(string $family, string $remoteId, array $remote, array &$requirements): void
    {
        if ($family !== FazerCardsIds::GAMEKEY) {
            return;
        }
        $restricted = filter_var(
            $remote['region_restriction'] ?? $requirements['regionRestriction'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $requirements['regionRestriction'] = $restricted;
        if (! $restricted) {
            return;
        }
        $cachedAt = trim((string) ($requirements['regionAvailabilityAt'] ?? ''));
        if ($cachedAt !== '') {
            try {
                if (Carbon::parse($cachedAt)->greaterThan(now()->subDay())) {
                    return;
                }
            } catch (\Throwable) {
            }
        }
        try {
            $payload = $this->client->fetch('gamekeys/region-restriction', 'GET', ['game_id' => $remoteId]);
            $data = FazerCardsPayload::unwrap($payload['data']);
            $requirements['regionAvailability'] = [
                'available' => is_array($data['available'] ?? null) ? array_values($data['available']) : [],
                'unavailable' => is_array($data['unavailable'] ?? null) ? array_values($data['unavailable']) : [],
                'regionType' => $data['region_type'] ?? $data['regionType'] ?? null,
            ];
            $requirements['regionAvailabilityAt'] = now()->toIso8601String();
        } catch (ApiException $e) {
            if (in_array($e->errorCode, ['SUPPLIER_UNREACHABLE', 'SUPPLIER_UNAVAILABLE', 'RATE_LIMIT_EXCEEDED'], true)) {
                throw $e;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $state
     */
    private function recordIncomplete(array $spec, array &$state): void
    {
        $remote = max($this->listedShop->lastRemoteTotal, (int) ($this->listedShop->lastRemoteTotal));
        $selected = $this->listedShop->lastSelectedTotal;
        if ($remote < 1 || $selected >= $remote) {
            return;
        }
        $family = (string) ($spec['family'] ?? $state['phase'] ?? '');
        $row = [
            'family' => $family,
            'synced' => $selected,
            'total' => $remote,
            'message' => 'همگام‌سازی ناقص: '.$selected.' از '.$remote,
        ];
        $this->syncIncomplete[] = $row;
        $state['syncIncomplete'] = $this->syncIncomplete;
    }

    /**
     * GET /manual-services/{id}/offers برای replenishment فیلدها را با code/name می‌دهد.
     *
     * @param  array<string, mixed>  $detail
     * @return list<array<string, mixed>>
     */
    private function manualInputsFromDetail(array $detail): array
    {
        $fields = $detail['fields'] ?? [];
        if (! is_array($fields)) {
            return [];
        }
        $out = [];
        foreach ($fields as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['code'] ?? $row['key'] ?? $row['name'] ?? ''));
            if ($key === '' || ForbiddenBuyerField::matches($key)) {
                continue;
            }
            $label = trim((string) ($row['name'] ?? $row['label'] ?? $row['title'] ?? ''));
            $out[] = [
                'name' => $key,
                'label' => $label,
                'type' => trim((string) ($row['type'] ?? 'text')) ?: 'text',
                'required' => (bool) ($row['required'] ?? $row['is_required'] ?? true),
                'options' => $row['options'] ?? $row['values'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  list<string>  $idKeys
     * @return list<array<string, mixed>>
     */
    private function filterStockedOffers(array $offers, array $idKeys): array
    {
        $out = [];
        foreach ($offers as $offer) {
            if ($this->stockedCatalogOffer($offer, $idKeys)) {
                $out[] = $offer;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  list<string>  $idKeys
     */
    private function stockedCatalogOffer(array $offer, array $idKeys): bool
    {
        if ($this->stringId($offer, $idKeys) === '') {
            return false;
        }
        if (array_key_exists('stock', $offer) && (int) $offer['stock'] <= 0) {
            return false;
        }
        $min = (int) ($offer['min_order_quantity'] ?? 1);

        return $min >= 1;
    }

    /**
     * @param  list<string|array<string, mixed>>  $inputs
     * @return list<string|array<string, mixed>>
     */
    private function productInputs(string $family, ?Product $existing, array $inputs): array
    {
        if (in_array($family, [FazerCardsIds::GAMEKEY, FazerCardsIds::GIFTCARD], true)) {
            return [];
        }
        if (in_array($family, [
            FazerCardsIds::STEAM_TOPUP,
            FazerCardsIds::STEAM_GIFT,
            FazerCardsIds::TELEGRAM_STARS,
            FazerCardsIds::TELEGRAM_PREMIUM,
        ], true)) {
            return [];
        }
        if (in_array($family, [FazerCardsIds::TOPUP, FazerCardsIds::MANUAL], true)) {
            return $inputs;
        }
        if ($existing && is_array($existing->inputs) && $existing->inputs !== []) {
            return $existing->inputs;
        }

        return $inputs;
    }

    private function namedGroup(string $slug, string $title, int $sort, string $titleEn = ''): CatalogGroup
    {
        $existing = CatalogGroup::query()->where('slug', $slug)->first();
        if ($existing) {
            $dirty = false;
            if (! $existing->title) {
                $existing->title = $title;
                $dirty = true;
            }
            if ($titleEn !== '' && ! filled($existing->name_en)) {
                $existing->name_en = $titleEn;
                $dirty = true;
            }
            if ($dirty) {
                $existing->save();
            }

            return $existing;
        }

        return CatalogGroup::query()->create([
            'name' => $titleEn !== '' ? $titleEn : $title,
            'title' => $title,
            'name_en' => $titleEn !== '' ? $titleEn : null,
            'slug' => $slug,
            'visible' => true,
            'sort_order' => $sort,
        ]);
    }

    private function maybeSetGroupCover(CatalogGroup $group, string $cover): void
    {
        $cover = trim($cover);
        if ($cover === '' || str_contains($cover, 'pubg-mobile.svg')) {
            return;
        }
        $current = trim((string) $group->image_url);
        if ($current !== '' && ! str_contains($current, 'pubg-mobile.svg')) {
            return;
        }
        if ($current === $cover) {
            return;
        }
        $group->image_url = $cover;
        $group->save();
    }

    private function isAdminUploadedCover(string $src): bool
    {
        $src = trim($src);
        if ($src === '' || str_starts_with($src, '/image/')) {
            return false;
        }
        if (str_starts_with($src, '/storage/')) {
            return true;
        }
        $path = parse_url($src, PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/storage/');
    }

    private function keepShopCover(?Product $existing, string $incoming): string
    {
        $current = trim((string) ($existing?->image_url ?: $existing?->thumbnail));
        if ($current === '' || str_contains($current, 'pubg-mobile.svg')) {
            return $incoming;
        }
        if ($this->isAdminUploadedCover($current)) {
            return $current;
        }
        if (FazerCardsMedia::isLegacySteamHeader($current) && $incoming !== '' && ! FazerCardsMedia::isLegacySteamHeader($incoming)) {
            return $incoming;
        }

        return $current;
    }

    private function keepFilled(?string $current, string $fallback): string
    {
        $current = trim((string) $current);

        return $current !== '' ? $current : $fallback;
    }

    private function overflowSubtitle(string $text): bool
    {
        return str_contains($text, "\n") || mb_strlen($text) > 160;
    }

    private function shopSubtitle(string $incoming, string $fallback): string
    {
        $incoming = trim($incoming);
        $fallback = trim($fallback);
        $chosen = ($incoming !== '' && ! $this->overflowSubtitle($incoming)) ? $incoming : $fallback;
        if ($chosen === '' && $incoming !== '') {
            $chosen = (string) preg_replace('/\s+/u', ' ', $incoming);
        }

        return $this->clipColumn($chosen, 160);
    }

    private function shopDescription(?string $existing, string $note): ?string
    {
        $kept = CatalogAttr::multiline($existing);
        if ($kept !== null) {
            return $kept;
        }

        return $this->overflowSubtitle($note) ? CatalogAttr::multiline($note) : null;
    }

    private function clipColumn(string $value, int $max): string
    {
        return mb_substr(trim($value), 0, $max);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function stringId(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }
            if (is_scalar($row[$key])) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function displayName(array $row, string $fallback): string
    {
        $name = $this->stringId($row, ['name', 'GameName', 'title', 'label', 'offer_name', 'card_name']);

        return $name !== '' ? $name : $fallback;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function usd(array $row): string
    {
        return Money::string($row['price_usd'] ?? $row['usd'] ?? $row['cost_usd'] ?? $row['unit_price'] ?? $row['price'] ?? '0');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function imageOf(array $row, string $fallback): string
    {
        $image = FazerCardsMedia::absolute(
            $this->stringId($row, ['imageurl', 'image_url', 'image', 'icon', 'thumbnail', 'cover']),
        );
        if ($image !== '') {
            return $image;
        }
        $steamId = $this->stringId($row, ['appid', 'app_id']);
        $steam = $this->steamCovers->url($steamId);
        if ($steam === '' || FazerCardsMedia::isLegacySteamHeader($steam)) {
            $steam = FazerCardsMedia::steamHeader($steamId);
        }
        if ($steam !== '') {
            return $steam;
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private function listOrSelf(array $payload, array $keys): array
    {
        $list = FazerCardsPayload::listFrom($payload, $keys);
        if ($list !== []) {
            return $list;
        }
        $data = FazerCardsPayload::unwrap($payload);

        return $data !== [] ? [$data] : [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private function listFromRow(array $row, array $keys): array
    {
        return FazerCardsPayload::listFrom($row, $keys);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function forbidden(string $name, array $row): bool
    {
        $hay = $name.' '.$this->stringId($row, ['category_id', 'id', 'slug', 'name']);

        return FazerCardsSeeds::isForbiddenStorefront($hay);
    }

    private function supplierCatalog(): ProductSupplierCatalog
    {
        return app(ProductSupplierCatalog::class);
    }

    private function candidateHandle(string $family, string $remoteId): string
    {
        $base = Str::slug($family.'-'.$remoteId);
        if ($base === '') {
            $base = 'fc-'.$family;
        }

        return mb_substr($base, 0, 100);
    }

    private function makeHandle(string $family, string $remoteId): string
    {
        $base = Str::slug($family.'-'.$remoteId);
        if ($base === '') {
            $base = 'fc-'.$family;
        }

        $handle = mb_substr($base, 0, 100);
        $n = 2;
        while (
            Product::query()
                ->where('handle', $handle)
                ->where(function ($query) use ($family, $remoteId) {
                    $query->whereNull('fazercards_category_id')
                        ->orWhere('fazercards_category_id', '!=', FazerCardsIds::productKey($family, $remoteId));
                })
                ->exists()
        ) {
            $handle = mb_substr($base.'-'.$n, 0, 100);
            $n++;
        }

        return $handle;
    }

    /**
     * @return list<string>
     */
    private function validateSupportedIds(): array
    {
        if ($this->validateSupportedIds !== null) {
            return $this->validateSupportedIds;
        }
        try {
            $this->validateSupportedIds = FazerCardsValidateId::idsFromCatalog($this->client->validateIdCatalog());
        } catch (\Throwable) {
            $this->validateSupportedIds = FazerCardsValidateId::SUPPORTED;
        }
        if ($this->validateSupportedIds === []) {
            $this->validateSupportedIds = FazerCardsValidateId::SUPPORTED;
        }

        return $this->validateSupportedIds;
    }
}
