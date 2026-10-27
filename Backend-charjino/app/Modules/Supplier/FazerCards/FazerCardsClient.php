<?php

namespace App\Modules\Supplier\FazerCards;

use App\Exceptions\ApiException;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FazerCardsClient
{
    public ?int $lastListTotal = null;

    public function __construct(private readonly FazerCardsCredentials $credentials) {}

    public function configured(): bool
    {
        return $this->credentials->configured();
    }

    public function liveOrderEnabled(): bool
    {
        return $this->credentials->liveOrderEnabled();
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return [
            'provider' => 'fazercards',
            'configured' => $this->configured(),
            'liveOrderEnabled' => $this->liveOrderEnabled(),
            'webhookConfigured' => $this->credentials->webhookConfigured(),
            'host' => parse_url($this->credentials->base(), PHP_URL_HOST),
            'keyLast4' => $this->credentials->keyLast4(),
            'docs' => 'https://api.fzr.cards/public/docs',
            'catalogScopes' => FazerCardsCatalogScopes::publicList(),
        ];
    }

    /**
     * Safe GET/PUT/DELETE/PATCH (non-gated). Blocks paid POST and validate POST.
     *
     * @param  array<string, mixed>  $query
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function fetch(string $path, string $method = 'GET', array $query = [], mixed $body = null, ?int $timeoutSeconds = null): array
    {
        $method = strtoupper($method);
        $rel = $this->relative($path);
        if ($this->isPaidPost($rel, $method) || $this->isInvoicePost($rel, $method) || $this->isGatedPatch($rel, $method)) {
            throw new ApiException('درخواست پولی باید از هلپر گیت‌شده عبور کند.', 403, 'PAID_BLOCKED_ON_FETCH');
        }
        if ($method === 'POST' && FazerCardsAllowlist::isDangerousPost($rel)) {
            throw new ApiException('این عملیات باید با تأیید صریح از پروکسی ادمین عبور کند.', 403, 'LIVE_ORDER_UNCONFIRMED');
        }
        if ($method === 'POST' && $rel === 'topups/validate-id') {
            throw new ApiException('اعتبارسنجی باید از هلپر نگاشت category_id عبور کند.', 403, 'VALIDATE_USE_HELPER');
        }
        if ($method === 'POST' && preg_match('#^manual-services/orders/[^/]+/chat$#', $rel) === 1) {
            throw new ApiException('چت دستی باید به‌صورت multipart ارسال شود.', 403, 'CHAT_USE_HELPER');
        }
        if ($method === 'POST' && preg_match('#^payments/[^/]+/verify$#', $rel) === 1) {
            throw new ApiException('تأیید Binance باید از هلپر binanceOrderId عبور کند.', 403, 'VERIFY_USE_HELPER');
        }
        if ($method === 'GET' && $rel === 'steam-gifts/games') {
            unset($query['limit']);
            $wait = $timeoutSeconds ?? (app()->environment('testing') ? 5 : 120);

            return $this->request($path, 'GET', $query, $body, [], 0, $wait);
        }
        if ($method === 'GET' && preg_match('#^steam-gifts/games/(\d+)$#', $rel) === 1) {
            $wait = $timeoutSeconds ?? (app()->environment('testing') ? 5 : 10);

            return $this->request($path, 'GET', $query, $body, [], 0, $wait);
        }

        return $this->request($path, $method, $query, $body, [], 0, $timeoutSeconds);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function request(string $path, string $method = 'GET', array $query = [], mixed $body = null, array $extraHeaders = [], int $attempt = 0, ?int $timeoutSeconds = null, bool $asMultipart = false): array
    {
        $key = $this->apiKey();
        $url = $this->buildUrl($path, $query);
        $started = microtime(true);
        $method = strtoupper($method);
        $rel = $this->relative($path);
        if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)
            && FazerCardsAllowlist::supportsIdempotency($rel)
            && ! isset($extraHeaders['Idempotency-Key'])) {
            $extraHeaders['Idempotency-Key'] = (string) Str::uuid();
        }
        if (! FazerCardsAllowlist::supportsIdempotency($rel)) {
            unset($extraHeaders['Idempotency-Key']);
        }
        $headers = array_merge([
            'X-API-Key' => $key,
            'Accept' => 'application/json',
        ], $asMultipart ? [] : ['Content-Type' => 'application/json'], $extraHeaders);

        $wait = $timeoutSeconds ?? ($method === 'GET' ? 18 : 45);
        $connect = min(8, max(2, (int) floor($wait / 2)));
        $lane = FazerCardsRequestLane::current();
        FazerCardsRateLimiter::before($lane);
        $pending = Http::withHeaders($headers)
            ->acceptJson()
            ->connectTimeout($connect)
            ->timeout($wait)
            ->withOptions(['read_timeout' => $wait, 'timeout' => $wait, 'connect_timeout' => $connect]);
        if ($asMultipart) {
            $pending = $pending->asMultipart();
        }

        try {
            $payload = is_array($body) ? $body : [];
            $response = match (strtoupper($method)) {
                'POST' => $pending->post($url, $payload),
                'PATCH' => $pending->patch($url, $payload),
                'PUT' => $pending->put($url, $payload),
                'DELETE' => $pending->delete($url),
                default => $pending->get($url),
            };
        } catch (ConnectionException) {
            $idempotent = FazerCardsAllowlist::supportsIdempotency($rel)
                && isset($extraHeaders['Idempotency-Key'])
                && trim((string) $extraHeaders['Idempotency-Key']) !== '';
            $canRetry = $attempt < 1 && $timeoutSeconds === null && ($method === 'GET' || $idempotent);
            if ($canRetry) {
                if (! app()->environment('testing')) {
                    sleep(1);
                }

                return $this->request($path, $method, $query, $body, $extraHeaders, $attempt + 1, $timeoutSeconds, $asMultipart);
            }

            throw new ApiException('اتصال به FazerCards برقرار نشد.', 503, 'SUPPLIER_UNREACHABLE');
        }

        $latencyMs = (int) round((microtime(true) - $started) * 1000);
        $this->observeRateLimit($rel, $lane, $response);
        $data = $response->json() ?? [];
        if (! is_array($data)) {
            $data = [];
        }

        if ($response->status() === 429) {
            $retryAfter = $this->retryAfterSeconds($response->header('Retry-After'));
            if ($method === 'GET' && $attempt < 3) {
                $this->waitRetryAfter($retryAfter);

                return $this->request($path, $method, $query, $body, $extraHeaders, $attempt + 1, $timeoutSeconds, $asMultipart);
            }
            FazerCardsError::throwFrom($data, 429, 'RATE_LIMIT_EXCEEDED', $retryAfter);
        }

        if (($data['ok'] ?? null) === false) {
            FazerCardsError::throwFrom($data, $response->status());
        }

        if (! $response->successful()) {
            FazerCardsError::throwFrom($data, $response->status());
        }

        return [
            'status' => $response->status(),
            'latencyMs' => $latencyMs,
            'data' => $data,
        ];
    }

    /**
     * @param  list<string>  $listKeys
     * @param  array<string, mixed>  $query
     * @return array{items: list<array<string, mixed>>, nextCursor: string, hasMore: bool}
     */
    public function page(string $path, array $listKeys = ['items'], array $query = [], ?string $cursor = null): array
    {
        $pageQuery = $query;
        $omitLimit = ! empty($pageQuery['_omit_limit']);
        unset($pageQuery['_omit_limit']);
        if (! $omitLimit && ! isset($pageQuery['limit'])) {
            $pageQuery['limit'] = 50;
        }
        if (is_string($cursor) && $cursor !== '') {
            $pageQuery['cursor'] = $cursor;
        }
        $result = $this->fetch($path, 'GET', $pageQuery);
        $items = FazerCardsPayload::listFrom($result['data'], $listKeys);
        $meta = is_array($result['data']['meta'] ?? null) ? $result['data']['meta'] : [];
        if (isset($meta['total']) && is_numeric($meta['total'])) {
            $this->lastListTotal = (int) $meta['total'];
        } elseif ($cursor === null) {
            $this->lastListTotal = null;
        }
        $next = trim((string) ($meta['next_cursor'] ?? ''));
        $hasMore = ! empty($meta['has_more']) && $next !== '';

        return [
            'items' => $items,
            'nextCursor' => $hasMore ? $next : '',
            'hasMore' => $hasMore,
        ];
    }

    /**
     * @param  list<string>  $listKeys
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function paginate(string $path, array $listKeys = ['items'], array $query = [], int $maxPages = 10000): array
    {
        $all = [];
        $cursor = null;
        for ($i = 0; $i < $maxPages; $i++) {
            $page = $this->page($path, $listKeys, $query, $cursor);
            $all = array_merge($all, $page['items']);
            if (! $page['hasMore']) {
                break;
            }
            $cursor = $page['nextCursor'];
        }

        return $all;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allTopups(): array
    {
        return $this->paginate('topups', ['items', 'categories'], ['include_ui' => '1', '_omit_limit' => true]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allGiftcards(): array
    {
        return $this->paginate('giftcards', ['items', 'categories'], ['include_ui' => '1', '_omit_limit' => true]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allGamekeys(): array
    {
        return $this->paginate('gamekeys', ['items', 'games'], ['include_ui' => '1', '_omit_limit' => true]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allManualServices(): array
    {
        $result = $this->fetch('manual-services', 'GET', ['include_ui' => '1']);

        return FazerCardsPayload::listFrom($result['data'], ['items', 'services']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allSteamGames(): array
    {
        $result = $this->fetch('steam-gifts/games', 'GET', []);

        return FazerCardsPayload::listFrom($result['data'], ['games', 'items']);
    }

    /**
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function checkSteamLogin(string $steamLogin): array
    {
        return $this->request('steam-topup/check-login', 'POST', [], [
            'steamLogin' => $steamLogin,
        ]);
    }

    /**
     * POST /funpay/login — فقط با تأیید. apiKey برگشتی کلید کاتالوگ Reseller نیست و ذخیره نمی‌شود.
     *
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function funpayLogin(string $email, string $password): array
    {
        $email = trim($email);
        $password = trim($password);
        if ($email === '' || $password === '') {
            throw new ApiException('ایمیل و رمز FunPay لازم است.', 400, 'MISSING_REQUIRED_FIELD');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('ایمیل FunPay نامعتبر است.', 400, 'INVALID_EMAIL');
        }

        return $this->request('funpay/login', 'POST', [], [
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * GET /funpay/me — پروفایل، موجودی، اشتراک اصلی و ادون funpay_integration.
     *
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function funpayMe(): array
    {
        return $this->fetch('funpay/me');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function validateIdCatalog(): array
    {
        return Cache::remember('fazer:validate-id:catalog', 900, function () {
            $catalog = $this->request('topups/validate-id');

            return FazerCardsPayload::listFrom($catalog['data'], ['items']);
        });
    }

    public function validatePlayerId(string $orderCategoryId, array $fields): array
    {
        $items = $this->validateIdCatalog();
        $supportedIds = [];
        foreach ($items as $row) {
            $id = trim((string) ($row['category_id'] ?? ''));
            if ($id !== '') {
                $supportedIds[] = $id;
            }
        }
        $validateCategoryId = FazerCardsValidateId::resolve($orderCategoryId, $supportedIds);
        if ($validateCategoryId === null) {
            throw new ApiException(
                'اعتبارسنجی شناسه برای این دسته در API موجود نیست. شناسه را در فروشگاه تأیید کن.',
                422,
                'ID_VALIDATION_UNSUPPORTED'
            );
        }

        $catalogItem = [];
        foreach ($items as $row) {
            if (($row['category_id'] ?? '') === $validateCategoryId) {
                $catalogItem = $row;
                break;
            }
        }
        $keys = FazerCardsValidateId::fieldKeys($catalogItem);
        $posted = FazerCardsValidateId::pickFields($keys !== [] ? $keys : ['player_id'], $fields);
        $result = $this->request('topups/validate-id', 'POST', [], [
            'category_id' => $validateCategoryId,
            'fields' => $posted,
        ]);
        $result['data'] = array_merge($result['data'], [
            'orderCategoryId' => $orderCategoryId,
            'validateCategoryId' => $validateCategoryId,
            'mapped' => $validateCategoryId !== $orderCategoryId,
            'fieldKeys' => $keys,
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function topupOrder(array $input): array
    {
        $categoryId = trim((string) ($input['category_id'] ?? ''));
        $offerId = trim((string) ($input['offer_id'] ?? ''));
        if ($categoryId === '' || $offerId === '') {
            throw new ApiException('شناسهٔ دسته و بسته لازم است.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $this->gatedDebit('topups/order', [
            'category_id' => $categoryId,
            'offer_id' => $offerId,
            'fields' => is_array($input['fields'] ?? null) ? $input['fields'] : [],
        ], $input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function giftcardOrder(array $input): array
    {
        $categoryId = trim((string) ($input['category_id'] ?? ''));
        $cardId = trim((string) ($input['card_id'] ?? ''));
        $quantity = max(1, min(100, (int) ($input['quantity'] ?? 1)));
        if ($categoryId === '' || $cardId === '') {
            throw new ApiException('شناسهٔ دسته و کارت لازم است.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $this->gatedDebit('giftcards/order', [
            'category_id' => $categoryId,
            'card_id' => $cardId,
            'quantity' => $quantity,
        ], $input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function gamekeyOrder(array $input): array
    {
        $gameId = trim((string) ($input['game_id'] ?? ''));
        $keyId = trim((string) ($input['key_id'] ?? ''));
        if ($gameId === '' || $keyId === '') {
            throw new ApiException('شناسهٔ بازی و کلید لازم است.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $this->gatedDebit('gamekeys/order', [
            'game_id' => $gameId,
            'key_id' => $keyId,
            'quantity' => max(1, min(100, (int) ($input['quantity'] ?? 1))),
        ], $input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function steamTopupOrder(array $input): array
    {
        return $this->gatedDebit('steam-topup/order', [
            'steamLogin' => (string) ($input['steamLogin'] ?? ''),
            'currency' => (string) ($input['currency'] ?? 'USD'),
            'amount' => (string) ($input['amount'] ?? ''),
        ], $input);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $opts
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function steamGiftOrder(array $body, array $opts): array
    {
        $invite = FazerCardsSteamGiftQuotes::normalizeInviteUrl((string) ($body['invite_url'] ?? ''));
        $subId = (int) ($body['sub_id'] ?? 0);
        $appId = (int) ($body['app_id'] ?? $body['appid'] ?? 0);
        $region = strtoupper(trim((string) ($body['region'] ?? '')));
        if (! FazerCardsSteamGiftQuotes::inviteAllowed($invite)) {
            throw new ApiException('لینک دعوت استیم نامعتبر است.', 400, 'MISSING_REQUIRED_FIELD');
        }
        if ($subId <= 0 || $appId <= 0 || $region === '') {
            throw new ApiException('شناسهٔ بازی، بسته و منطقه لازم است.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $this->gatedDebit('steam-gifts/order', [
            'invite_url' => $invite,
            'sub_id' => $subId,
            'app_id' => $appId,
            'region' => $region,
        ], $opts);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function telegramStarsBuy(array $input): array
    {
        $username = trim((string) ($input['telegram_username'] ?? ''));
        $quantity = (int) ($input['quantity'] ?? 0);
        if ($username === '') {
            throw new ApiException('نام کاربری تلگرام را وارد کن.', 400, 'MISSING_REQUIRED_FIELD');
        }
        if (! FazerCardsTelegramQuotes::quantityAllowed($quantity)) {
            throw new ApiException('تعداد ستاره باید بین ۵۰ و ۱۰٬۰۰۰ باشد.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $this->gatedDebit('telegram/stars/buy', [
            'telegram_username' => $username,
            'quantity' => $quantity,
        ], $input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function telegramPremiumBuy(array $input): array
    {
        $username = trim((string) ($input['telegram_username'] ?? ''));
        $months = (int) ($input['months'] ?? 0);
        if ($username === '') {
            throw new ApiException('نام کاربری تلگرام را وارد کن.', 400, 'MISSING_REQUIRED_FIELD');
        }
        if (! in_array($months, FazerCardsTelegramQuotes::PREMIUM_MONTHS, true)) {
            throw new ApiException('مدت پرمیوم باید ۳، ۶ یا ۱۲ ماه باشد.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $this->gatedDebit('telegram/premium/buy', [
            'telegram_username' => $username,
            'months' => $months,
        ], $input);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $opts
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function manualServiceOrder(array $body, array $opts): array
    {
        $serviceId = trim((string) ($body['manual_service_id'] ?? $body['service_id'] ?? ''));
        $productId = trim((string) ($body['product_id'] ?? $body['offer_id'] ?? ''));
        if ($serviceId === '' || $productId === '') {
            throw new ApiException('شناسهٔ سرویس و محصول لازم است.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $this->gatedDebit('manual-services/order', [
            'manual_service_id' => $serviceId,
            'product_id' => $productId,
            'fields' => is_array($body['fields'] ?? null) ? $body['fields'] : [],
        ], $opts);
    }

    /**
     * POST /manual-services/orders/{orderId}/chat — multipart `body` (تصویر از فروشگاه ارسال نمی‌شود).
     *
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function manualChatPost(string $orderId, string $text): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            throw new ApiException('شناسهٔ سفارش لازم است.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $this->request(
            'manual-services/orders/'.$orderId.'/chat',
            'POST',
            [],
            ['body' => $text],
            [],
            0,
            null,
            true,
        );
    }

    /**
     * GET /orders — page از ۱، limit پیش‌فرض ۵۰ سقف ۱۰۰.
     *
     * @param  array<string, mixed>  $query
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function listOrders(array $query = []): array
    {
        return $this->fetch('orders', 'GET', [
            'page' => FazerCardsBilling::page($query['page'] ?? 1),
            'limit' => FazerCardsBilling::limit($query['limit'] ?? 50),
        ]);
    }

    /**
     * GET /orders/{orderId} — شناسهٔ عمومی مثل ord-1.
     *
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function getSupplierOrder(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            throw new ApiException('شناسهٔ سفارش لازم است.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $this->fetch('orders/'.$orderId);
    }

    /**
     * GET /subscription/activation-quote — فقط وقتی اشتراک غیرفعال است.
     *
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function activationQuote(string $plan, ?string $promoCode = null): array
    {
        $query = ['plan' => FazerCardsBilling::plan($plan)];
        $promo = trim((string) $promoCode);
        if ($promo !== '') {
            $query['promo_code'] = $promo;
        }

        return $this->fetch('subscription/activation-quote', 'GET', $query);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function activateSubscription(array $input): array
    {
        $plan = FazerCardsBilling::plan((string) ($input['plan'] ?? ''));
        $promo = trim((string) ($input['promo_code'] ?? ''));
        $quote = $this->activationQuote($plan, $promo !== '' ? $promo : null);
        $required = (string) ($quote['data']['chargedAmountUsd'] ?? '');
        $this->assertLiveAndConfirm((bool) ($input['confirm'] ?? false));
        $this->requireSpendableWallet($required !== '' ? $required : null);
        $body = ['plan' => $plan];
        if ($promo !== '') {
            $body['promo_code'] = $promo;
        }

        return $this->request('subscription/activate-from-balance', 'POST', [], $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function patchSubscription(string $path, array $body, bool $confirm): array
    {
        $rel = $this->relative($path);
        if (! FazerCardsAllowlist::isGatedPatch($rel)) {
            throw new ApiException('این مسیر PATCH مجاز نیست.', 404, 'UNKNOWN_ENDPOINT');
        }
        $this->assertLiveAndConfirm($confirm);
        if ($rel === 'subscription/auto-renew') {
            $body = ['planAutoRenew' => FazerCardsBilling::flag($body['planAutoRenew'] ?? $body['enabled'] ?? false)];
        }
        if ($rel === 'subscription/plan') {
            $plan = FazerCardsBilling::plan((string) ($body['plan'] ?? ''));
            $current = FazerCardsPayload::unwrap($this->fetch('subscription')['data']);
            $active = (bool) ($current['subscriptionActive'] ?? false);
            $from = (string) ($current['plan'] ?? '');
            if ($active && FazerCardsBilling::isUpgrade($from, $plan)) {
                $this->requireSpendableWallet($this->planListPriceUsd($plan));
            }
            $body = ['plan' => $plan];
        }

        return $this->request($rel, 'PATCH', [], $body);
    }

    /**
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function createPayment(string $method, mixed $amount, bool $confirm): array
    {
        if (! $confirm) {
            throw new ApiException('تأیید صریح برای ساخت فاکتور لازم است.', 403, 'LIVE_ORDER_UNCONFIRMED');
        }
        $method = trim($method);
        $usd = is_numeric($amount) ? (float) $amount : 0.0;
        if ($method === '' || $usd <= 0) {
            throw new ApiException('روش پرداخت و مبلغ USD لازم است.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $this->request('payments/create', 'POST', [], [
            'method' => $method,
            'amount' => $usd,
        ]);
    }

    /**
     * POST /payments/{paymentId}/verify — فقط Binance Pay با binanceOrderId.
     *
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function verifyPayment(string $paymentId, string $binanceOrderId): array
    {
        $paymentId = trim($paymentId);
        $binanceOrderId = trim($binanceOrderId);
        if ($paymentId === '' || $binanceOrderId === '') {
            throw new ApiException('شناسهٔ پرداخت و binanceOrderId لازم است.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $this->request('payments/'.$paymentId.'/verify', 'POST', [], [
            'binanceOrderId' => $binanceOrderId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $opts
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    private function gatedDebit(string $path, array $body, array $opts): array
    {
        $this->assertLiveAndConfirm((bool) ($opts['confirm'] ?? false));
        $required = isset($opts['priceUsd']) ? (string) $opts['priceUsd'] : (isset($opts['price_usd']) ? (string) $opts['price_usd'] : null);
        $this->requireSpendableWallet($required !== '' ? $required : null);
        $headers = [];
        if (FazerCardsAllowlist::supportsIdempotency($path)) {
            $idempotency = trim((string) ($opts['idempotencyKey'] ?? $opts['idempotency_key'] ?? ''));
            if ($idempotency === '') {
                $idempotency = (string) Str::uuid();
            }
            $headers['Idempotency-Key'] = $idempotency;
        }

        return $this->request($path, 'POST', [], $body, $headers);
    }

    private function assertLiveAndConfirm(bool $confirm): void
    {
        if (! $this->liveOrderEnabled()) {
            throw new ApiException('سفارش زنده قفل است. FAZERCARDS_ALLOW_LIVE_ORDER باید true باشد.', 403, 'LIVE_ORDER_LOCKED');
        }
        if (! $confirm) {
            throw new ApiException('تأیید صریح برای درخواست پولی لازم است.', 403, 'LIVE_ORDER_UNCONFIRMED');
        }
    }

    private function planListPriceUsd(string $plan): ?string
    {
        $data = FazerCardsPayload::unwrap($this->fetch('subscription/plans')['data']);
        foreach ($data['plans'] ?? [] as $row) {
            if (! is_array($row) || strtolower((string) ($row['plan'] ?? '')) !== $plan) {
                continue;
            }
            $price = trim((string) ($row['priceUsd'] ?? ''));

            return $price !== '' ? $price : null;
        }

        return null;
    }

    private function requireSpendableWallet(?string $requiredUsd = null): string
    {
        $result = $this->request('balance');
        $balance = Money::string($result['data']['balance'] ?? '0');
        if (! Money::isPositive($balance)) {
            throw new ApiException('موجودی کیف پول دلاری تأمین‌کننده کافی نیست.', 400, 'INSUFFICIENT_BALANCE');
        }
        if (is_string($requiredUsd) && $requiredUsd !== '' && Money::compare($balance, $requiredUsd) < 0) {
            throw new ApiException('موجودی کیف پول دلاری تأمین‌کننده کافی نیست.', 400, 'INSUFFICIENT_BALANCE');
        }

        return $balance;
    }

    private function apiKey(): string
    {
        $key = $this->credentials->apiKey();
        if ($key === '') {
            throw new ApiException('کلید FazerCards تنظیم نشده است.', 503, 'SUPPLIER_NOT_CONFIGURED');
        }

        return $key;
    }

    private function relative(string $path): string
    {
        $p = str_starts_with($path, '/') ? substr($path, 1) : $path;
        $p = preg_replace('#^api/v2/#', '', $p) ?? $p;

        return trim($p, '/');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function buildUrl(string $path, array $query = []): string
    {
        $joined = '/'.$this->relative($path);
        $url = $this->credentials->base().$joined;
        $filtered = array_filter($query, fn ($value) => $value !== null && $value !== '');
        if ($filtered === []) {
            return $url;
        }

        return $url.'?'.http_build_query($filtered);
    }

    private function isPaidPost(string $path, string $method): bool
    {
        return $method === 'POST' && FazerCardsAllowlist::isPaidPost($path);
    }

    private function isInvoicePost(string $path, string $method): bool
    {
        return $method === 'POST' && FazerCardsAllowlist::isInvoicePost($path);
    }

    private function isGatedPatch(string $path, string $method): bool
    {
        return $method === 'PATCH' && FazerCardsAllowlist::isGatedPatch($path);
    }

    private function observeRateLimit(string $rel, string $lane, mixed $response): void
    {
        if (! is_object($response) || ! method_exists($response, 'header')) {
            return;
        }
        $catalogish = $rel !== 'me'
            && $rel !== 'balance'
            && $rel !== 'steam-gifts/games';
        FazerCardsRateLimiter::observe([
            'lane' => $lane,
            'path' => $rel,
            'catalogish' => $catalogish,
            'status' => (int) (method_exists($response, 'status') ? $response->status() : 0),
            'limit' => $response->header('X-RateLimit-Limit'),
            'remaining' => $response->header('X-RateLimit-Remaining'),
            'reset' => $response->header('X-RateLimit-Reset'),
            'retryAfter' => $response->header('Retry-After'),
        ]);
    }

    private function retryAfterSeconds(mixed $header): int
    {
        $raw = is_array($header) ? ($header[0] ?? '') : $header;
        $seconds = (int) $raw;

        return max(1, $seconds > 0 ? $seconds : 1);
    }

    private function waitRetryAfter(int $seconds): void
    {
        if (app()->environment('testing')) {
            return;
        }
        $jitter = $seconds * (0.85 + (mt_rand(0, 30) / 100));
        usleep((int) max(1, $jitter * 1_000_000));
    }
}
