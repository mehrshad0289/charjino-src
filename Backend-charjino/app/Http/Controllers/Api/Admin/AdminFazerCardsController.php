<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\FazerCardsAudit;
use App\Models\Product;
use App\Modules\Catalog\FazerCardsCatalogSync;
use App\Modules\Catalog\GameKeyPlatform;
use App\Modules\Catalog\SteamGiftBlocklist;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Modules\Supplier\FazerCards\FazerCardsAllowlist;
use App\Modules\Supplier\FazerCards\FazerCardsCatalogScopes;
use App\Modules\Supplier\FazerCards\FazerCardsClient;
use App\Modules\Supplier\FazerCards\FazerCardsCredentials;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsListedShop;
use App\Modules\Supplier\FazerCards\FazerCardsProxy;
use App\Modules\Supplier\FazerCards\FazerCardsSteamGiftCatalog;
use App\Modules\Supplier\FazerCards\FazerCardsSteamGiftShop;
use App\Modules\Supplier\FazerCards\FazerCardsSyncStatus;
use App\Support\ApiResponse;
use App\Support\AuthUser;
use App\Support\CustomerError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AdminFazerCardsController extends Controller
{
    public function show(
        FazerCardsClient $client,
        FazerCardsAdapter $adapter,
        FazerCardsCredentials $credentials,
    ): JsonResponse {
        $status = $client->status();
        $status['account'] = null;
        $status['accountError'] = null;
        $status['accountErrorCode'] = null;
        $status['endpoints'] = [
            'docs' => 'https://api.fzr.cards/public/docs',
            'paidPost' => FazerCardsAllowlist::PAID_POST,
            'invoicePost' => FazerCardsAllowlist::INVOICE_POST,
            'gatedPatch' => FazerCardsAllowlist::GATED_PATCH,
            'dangerousPost' => FazerCardsAllowlist::DANGEROUS_POST,
        ];
        if ($credentials->configured()) {
            try {
                $account = $adapter->account();
                $status['account'] = [
                    'email' => $account['email'],
                    'login' => $account['login'],
                    'plan' => $account['plan'],
                    'planExpiresAt' => $account['planExpiresAt'],
                    'planAutoRenew' => $account['planAutoRenew'] ?? false,
                    'wallet' => $account['wallet'],
                    'currency' => $account['currency'],
                    'active' => $account['active'],
                ];
            } catch (Throwable $e) {
                $code = $e instanceof ApiException ? $e->errorCode : 'SUPPLIER_UNREACHABLE';
                $status['accountError'] = CustomerError::message($code, $e->getMessage());
                $status['accountErrorCode'] = $code;
            }
        }
        $status['steamGiftBlockedWords'] = SteamGiftBlocklist::text();

        return ApiResponse::ok($status);
    }

    public function saveKey(Request $request, FazerCardsCredentials $credentials): JsonResponse
    {
        $data = $request->validate([
            'apiKey' => ['required', 'string', 'min:8', 'max:240'],
        ]);
        $key = trim((string) $data['apiKey']);
        $credentials->storeKey($key);
        $this->audit($request, 'save_key', 'PUT', 'key');

        return ApiResponse::ok([
            'configured' => $credentials->configured(),
            'keyLast4' => $credentials->keyLast4(),
        ], 'کلید ذخیره شد. مقدار کامل نمایش داده نمی‌شود.');
    }

    public function clearKey(Request $request, FazerCardsCredentials $credentials): JsonResponse
    {
        $credentials->storeKey('');
        $this->audit($request, 'clear_key', 'DELETE', 'key');

        return ApiResponse::ok([
            'configured' => false,
            'keyLast4' => null,
        ], 'کلید FazerCards از تنظیمات حذف شد.');
    }

    public function saveSettings(Request $request, FazerCardsCredentials $credentials): JsonResponse
    {
        $data = $request->validate([
            'allowLiveOrder' => ['required', 'boolean'],
        ]);
        $credentials->storeLiveOrder((bool) $data['allowLiveOrder']);
        $this->audit($request, 'save_settings', 'PUT', 'settings');

        return ApiResponse::ok([
            'liveOrderEnabled' => $credentials->liveOrderEnabled(),
            'webhookConfigured' => $credentials->webhookConfigured(),
        ], 'تنظیمات FazerCards ذخیره شد.');
    }

    public function saveSteamGiftBlocklist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'steamGiftBlockedWords' => ['required', 'string', 'max:4000'],
        ]);
        $text = SteamGiftBlocklist::putText((string) $data['steamGiftBlockedWords']);
        $this->audit($request, 'save_steam_gift_blocklist', 'PUT', 'steam-gift-blocklist');

        return ApiResponse::ok([
            'steamGiftBlockedWords' => $text,
        ], 'فهرست کلمات ممنوعه گیفت استیم ذخیره شد.');
    }

    public function audits(): JsonResponse
    {
        $items = FazerCardsAudit::query()
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(fn (FazerCardsAudit $row) => $row->toAdmin())
            ->values()
            ->all();

        return ApiResponse::ok(['items' => $items]);
    }

    public function sync(Request $request, FazerCardsCatalogSync $sync): JsonResponse
    {
        $family = trim((string) $request->input('family', ''));
        if ($family !== '' && ! FazerCardsCatalogScopes::isKnown($family)) {
            throw new ApiException('این دسته در کاتالوگ FazerCards نیست.', 422, 'INVALID_CATALOG_SCOPE');
        }
        $result = $sync->tick(null, null, $family !== '' ? $family : null);
        $status = (string) ($result['status'] ?? '');
        $label = (string) ($result['scopeLabel'] ?? 'FazerCards');
        $message = $status === 'completed'
            ? 'کاتالوگ «'.$label.'» همگام شد.'
            : (string) ($result['message'] ?? 'همگام تکه‌تکه ادامه دارد.');

        return ApiResponse::ok($result, $message);
    }

    public function cancelSync(FazerCardsCatalogSync $sync): JsonResponse
    {
        $result = $sync->cancel();

        return ApiResponse::ok($result, (string) ($result['message'] ?? 'همگام لغو شد.'));
    }

    public function syncStatus(): JsonResponse
    {
        return ApiResponse::ok(FazerCardsSyncStatus::get());
    }

    public function searchSteamGifts(
        Request $request,
        FazerCardsSteamGiftCatalog $catalog,
        FazerCardsSteamGiftShop $shop,
    ): JsonResponse {
        $query = trim((string) $request->query('q', ''));
        $rows = $catalog->search($query, 20);
        $items = [];
        foreach ($rows as $row) {
            $appid = (int) ($row['appid'] ?? 0);
            if ($appid <= 0) {
                continue;
            }
            $product = Product::query()
                ->where('fazercards_category_id', FazerCardsIds::productKey(FazerCardsIds::STEAM_GIFT, (string) $appid))
                ->first();
            $items[] = [
                'appid' => $appid,
                'name' => trim((string) ($row['name'] ?? '')),
                'inShop' => $product !== null,
                'visible' => $product ? (bool) $product->getAttribute('visible') : false,
                'productId' => $product?->id,
            ];
        }

        return ApiResponse::ok([
            'items' => $items,
            'shopMax' => $shop->shopMax(),
        ]);
    }

    public function importSteamGift(Request $request, FazerCardsCatalogSync $sync): JsonResponse
    {
        $data = $request->validate([
            'appId' => ['required', 'integer', 'min:1'],
        ]);
        $product = $sync->importSteamGift((int) $data['appId']);
        $this->audit($request, 'import_steam_gift', 'POST', 'steam-gifts/import');

        return ApiResponse::ok($product->loadCount('skus')->toAdmin(), 'به فروشگاه چارژینو اضافه شد.');
    }

    public function searchCatalog(
        Request $request,
        FazerCardsCatalogSync $sync,
        FazerCardsListedShop $listed,
        FazerCardsSteamGiftCatalog $catalog,
        FazerCardsSteamGiftShop $steamShop,
    ): JsonResponse {
        $family = strtolower(trim((string) $request->query('family', 'steam-gifts')));
        $query = trim((string) $request->query('q', ''));
        if ($family === 'steam-gifts') {
            $rows = $catalog->search($query, 20);
            $items = [];
            foreach ($rows as $row) {
                $appid = (int) ($row['appid'] ?? 0);
                if ($appid <= 0) {
                    continue;
                }
                $product = Product::query()
                    ->where('fazercards_category_id', FazerCardsIds::productKey(FazerCardsIds::STEAM_GIFT, (string) $appid))
                    ->first();
                $items[] = [
                    'family' => 'steam-gifts',
                    'remoteId' => (string) $appid,
                    'appid' => $appid,
                    'name' => trim((string) ($row['name'] ?? '')),
                    'inShop' => $product !== null,
                    'visible' => $product ? (bool) $product->getAttribute('visible') : false,
                    'productId' => $product?->id,
                ];
            }

            return ApiResponse::ok([
                'items' => $items,
                'shopMax' => $steamShop->shopMax(),
            ]);
        }

        $spec = $sync->familySpecFor($family);
        if ($spec === null || ! in_array($family, ['topups', 'giftcards', 'gamekeys', 'manual'], true)) {
            throw new ApiException('این دسته فهرست جستجوپذیر ندارد.', 422, 'INVALID_CATALOG_SCOPE');
        }
        $platform = trim((string) $request->query('platform', ''));
        $rows = $listed->search($spec, $query, 20, $platform);
        $items = [];
        foreach ($rows as $row) {
            $remoteId = $listed->remoteId($spec, $row);
            if ($remoteId === '') {
                continue;
            }
            $familyKey = (string) ($spec['family'] ?? '');
            $product = Product::query()
                ->where('fazercards_category_id', FazerCardsIds::productKey($familyKey, $remoteId))
                ->first();
            $items[] = [
                'family' => $family,
                'remoteId' => $remoteId,
                'name' => $listed->rowName($row) !== '' ? $listed->rowName($row) : $remoteId,
                'platform' => $family === 'gamekeys' ? GameKeyPlatform::fromFazer($row) : null,
                'inShop' => $product !== null,
                'visible' => $product ? (bool) $product->getAttribute('visible') : false,
                'productId' => $product?->id,
            ];
        }

        return ApiResponse::ok([
            'items' => $items,
            'shopMax' => $listed->shopMax($family === 'gamekeys' ? FazerCardsIds::GAMEKEY : $family),
            'perPlatform' => $family === 'gamekeys' ? $listed->gamekeysPerPlatform() : null,
            'platforms' => $family === 'gamekeys' ? $listed->lastGamekeyPlatforms() : [],
        ]);
    }

    public function importCatalog(Request $request, FazerCardsCatalogSync $sync): JsonResponse
    {
        $data = $request->validate([
            'family' => ['required', 'string', 'max:40'],
            'remoteId' => ['required', 'string', 'max:120'],
        ]);
        $product = $sync->importListed((string) $data['family'], (string) $data['remoteId']);
        $this->audit($request, 'import_catalog', 'POST', 'catalog/import');

        return ApiResponse::ok($product->loadCount('skus')->toAdmin(), 'به فروشگاه چارژینو اضافه شد.');
    }

    public function proxy(Request $request, FazerCardsProxy $proxy, string $path): JsonResponse
    {
        $query = [];
        foreach ($request->query() as $key => $value) {
            if (is_scalar($value)) {
                $query[(string) $key] = $value;
            }
        }
        $body = [];
        if (in_array($request->method(), ['POST', 'PATCH', 'PUT'], true)) {
            $json = $request->json()->all();
            $body = is_array($json) ? $json : [];
        }

        $ok = true;
        try {
            $result = $proxy->dispatch($request->method(), $path, $query, $body);
        } catch (Throwable $e) {
            $ok = false;
            $this->maybeAuditProxy($request, $path, $ok);
            throw $e;
        }
        $this->maybeAuditProxy($request, $path, $ok);

        return ApiResponse::ok($result);
    }

    private function maybeAuditProxy(Request $request, string $path, bool $ok): void
    {
        $joined = trim($path, '/');
        if (! FazerCardsAllowlist::requiresConfirm($request->method(), $joined)) {
            return;
        }
        $this->audit($request, 'proxy', $request->method(), $joined, $ok);
    }

    private function audit(Request $request, string $action, string $method, string $path, bool $ok = true): void
    {
        try {
            FazerCardsAudit::query()->create([
                'user_id' => AuthUser::id($request),
                'action' => $action,
                'method' => strtoupper($method),
                'path' => substr($path, 0, 190),
                'ok' => $ok,
            ]);
        } catch (Throwable) {
        }
    }
}
