<?php

namespace App\Modules\Supplier\Shop2;

use App\Exceptions\ApiException;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class Shop2Client
{
    public function base(): string
    {
        return rtrim((string) config('charjino.supplier.s2t_base'), '/');
    }

    public function configured(): bool
    {
        return trim((string) config('charjino.supplier.s2t_key')) !== '';
    }

    public function liveOrderEnabled(): bool
    {
        return (bool) config('charjino.supplier.allow_live_order');
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return [
            'configured' => $this->configured(),
            'liveOrderEnabled' => $this->liveOrderEnabled(),
            'host' => parse_url($this->base(), PHP_URL_HOST),
            'seeds' => array_values(array_filter(array_map(
                static fn (array $row): ?string => isset($row['internal_code']) ? (string) $row['internal_code'] : null,
                SkuMap::skus()
            ))) ?: ['SKU_PUBG_UC', 'SKU_COD_CP'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function account(): array
    {
        $result = $this->request('/account');
        $payload = $result['data'];
        $account = $payload['account'] ?? $payload['data'] ?? $payload;
        if (! is_array($account)) {
            $account = [];
        }

        return [
            'email' => (string) ($account['email'] ?? ''),
            'wallet' => $this->walletFromAccount($payload),
            'currency' => 'USD',
            'active' => (bool) ($account['active'] ?? $account['is_active'] ?? true),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bigCategories(): array
    {
        return Payload::listFrom($this->request('/catalog/big-categories')['data'], ['big_categories', 'bigCategories']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(int $bigCategoryId): array
    {
        return Payload::listFrom(
            $this->request('/catalog/categories', 'GET', ['bigCategoryId' => $bigCategoryId])['data'],
            ['categories']
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function subcategories(int $categoryId): array
    {
        return Payload::listFrom(
            $this->request('/catalog/subcategories', 'GET', ['categoryId' => $categoryId])['data'],
            ['subcategories']
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function price(int $itemId): array
    {
        $payload = $this->request('/catalog/subcategory/'.$itemId.'/price')['data'];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $price = is_array($data['price'] ?? null) ? $data['price'] : $data;

        return [
            'unit_price' => Payload::usdPrice(is_array($price) ? $price : ['unit_price' => $price]),
            'currency' => 'USD',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requirements(int $categoryId): array
    {
        return Payload::listFrom(
            $this->request('/catalog/category/'.$categoryId.'/requirements')['data'],
            ['requirements', 'fields']
        );
    }

    /**
     * @param  array<string, mixed>  $requirements
     * @return array<string, mixed>
     */
    public function validatePlayer(int $subCategoryId, array $requirements): array
    {
        $body = ['sub_category_id' => $subCategoryId];
        foreach (['player_id', 'server', 'zone_id'] as $key) {
            if (isset($requirements[$key]) && $requirements[$key] !== '') {
                $body[$key] = $requirements[$key];
            }
        }

        return $this->request('/player/validate', 'POST', [], $body)['data'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(string $orderId): array
    {
        return $this->request('/orders/'.$orderId)['data'];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function fetchPath(string $path, string $method = 'GET', array $query = [], mixed $body = null): array
    {
        if (preg_match('#/orders/create\b#i', $path)) {
            throw new ApiException('ساخت سفارش زنده از این متد ممنوع است.', 403, 'LIVE_ORDER_LOCKED');
        }

        return $this->request($path, $method, $query, $body);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    public function createOrder(array $input): array
    {
        if (! $this->liveOrderEnabled()) {
            throw new ApiException('ساخت سفارش زنده قفل است. ALLOW_LIVE_ORDER باید yes باشد.', 403, 'LIVE_ORDER_LOCKED');
        }
        if (empty($input['confirm'])) {
            throw new ApiException('تأیید صریح برای سفارش زنده لازم است.', 403, 'LIVE_ORDER_UNCONFIRMED');
        }

        $orderId = (string) ($input['order_id'] ?? '');
        if (! preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $orderId)) {
            throw new ApiException('شناسهٔ سفارش نامعتبر است.', 400, 'INVALID_UUID_FORMAT');
        }

        $account = $this->request('/account');
        $wallet = $this->walletFromAccount($account['data']);
        if (! Money::isPositive($wallet)) {
            throw new ApiException('موجودی کیف پول دلاری تأمین‌کننده کافی نیست.', 400, 'INSUFFICIENT_BALANCE');
        }

        OrderAttemptStore::remember($orderId, [
            'itemId' => (int) ($input['sub_category_id'] ?? 0),
            'expectedUnitPrice' => (string) ($input['expected_unit_price'] ?? ''),
        ]);

        return $this->request('/orders/create', 'POST', [], [
            'order_id' => $orderId,
            'sub_category_id' => $input['sub_category_id'],
            'quantity' => $input['quantity'] ?? 1,
            'requirements' => $input['requirements'] ?? [],
            'expected_unit_price' => $input['expected_unit_price'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function walletFromAccount(array $data): string
    {
        $account = $data['account'] ?? $data['data'] ?? $data;
        if (! is_array($account)) {
            return '0';
        }

        return (string) ($account['wallet'] ?? $account['balance'] ?? $account['wallet_usd'] ?? '0');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    private function request(string $path, string $method = 'GET', array $query = [], mixed $body = null, int $attempt = 0): array
    {
        $key = $this->apiKey();
        $url = $this->buildUrl($path, $query);
        $started = microtime(true);
        $host = (string) parse_url($this->base(), PHP_URL_HOST);
        $resolve = app(Shop2HostResolver::class)->curlResolve($host);

        $pending = Http::withToken($key)
            ->acceptJson()
            ->connectTimeout(15)
            ->timeout(30)
            ->withOptions([
                'force_ip_resolve' => 'v4',
                'curl' => array_filter([
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    CURLOPT_RESOLVE => $resolve !== [] ? $resolve : null,
                ]),
            ]);

        try {
            $response = $method === 'POST'
                ? $pending->post($url, is_array($body) ? $body : [])
                : $pending->get($url);
        } catch (ConnectionException $e) {
            throw new ApiException('اتصال به تأمین‌کننده برقرار نشد. DNS مودم دامنه Shop2 را پیدا نکرد.', 503, 'SUPPLIER_UNREACHABLE');
        }

        $latencyMs = (int) round((microtime(true) - $started) * 1000);
        $data = $response->json() ?? [];
        if (! is_array($data)) {
            $data = [];
        }

        if ($response->status() === 429 && $attempt < 3) {
            $retryAfter = (int) ($response->header('Retry-After') ?: ($data['error']['retry_after'] ?? 5));
            sleep(max(1, $retryAfter));

            return $this->request($path, $method, $query, $body, $attempt + 1);
        }

        if (! $response->ok() || ($data['success'] ?? null) === false) {
            Shop2Error::throwFrom($data, $response->status());
        }

        return [
            'status' => $response->status(),
            'latencyMs' => $latencyMs,
            'data' => $data,
        ];
    }

    private function apiKey(): string
    {
        $key = trim((string) config('charjino.supplier.s2t_key'));
        if ($key === '') {
            throw new ApiException('کلید تأمین‌کننده تنظیم نشده است.', 503, 'SUPPLIER_NOT_CONFIGURED');
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function buildUrl(string $path, array $query = []): string
    {
        $joined = str_starts_with($path, '/') ? $path : '/'.$path;
        $url = $this->base().$joined;
        $filtered = array_filter(
            $query,
            fn ($value) => $value !== null && $value !== ''
        );
        if ($filtered === []) {
            return $url;
        }

        return $url.'?'.http_build_query($filtered);
    }
}
