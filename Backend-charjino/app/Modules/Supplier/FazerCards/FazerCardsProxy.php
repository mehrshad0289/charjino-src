<?php

namespace App\Modules\Supplier\FazerCards;

use App\Exceptions\ApiException;

final class FazerCardsProxy
{
    public function __construct(
        private readonly FazerCardsClient $client,
        private readonly FazerCardsCredentials $credentials,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @return array{status: int, latencyMs: int, upstreamPath: string, data: array<string, mixed>}
     */
    public function dispatch(string $method, string $path, array $query = [], array $body = []): array
    {
        $method = strtoupper($method);
        $parts = array_values(array_filter(explode('/', trim($path, '/')), fn ($part) => $part !== ''));
        $joined = implode('/', $parts);
        $upstream = '/'.$joined;

        if ($parts === [] || ! FazerCardsAllowlist::allows($method, $parts)) {
            throw new ApiException('این مسیر FazerCards در allowlist نیست.', 404, 'UNKNOWN_ENDPOINT');
        }

        if ($this->queryFlag($query, 'confirm')) {
            $body['confirm'] = true;
        }

        if (FazerCardsAllowlist::requiresConfirm($method, $joined) && ! $this->confirmed($body)) {
            throw new ApiException('تأیید صریح برای این عملیات لازم است.', 403, 'LIVE_ORDER_UNCONFIRMED');
        }

        $clean = $body;
        unset($clean['confirm']);

        if ($method === 'POST' && $joined === 'topups/validate-id') {
            return $this->wrap($this->validate($body), $upstream);
        }

        if ($method === 'POST' && $this->isManualChat($parts)) {
            $text = trim((string) ($clean['body'] ?? $clean['message'] ?? ''));

            return $this->wrap($this->client->manualChatPost((string) ($parts[2] ?? ''), $text), $upstream);
        }

        if ($method === 'POST' && $joined === 'funpay/login') {
            $result = $this->client->funpayLogin(
                (string) ($clean['email'] ?? ''),
                (string) ($clean['password'] ?? ''),
            );

            return $this->wrap($result, $upstream);
        }

        if ($method === 'POST' && $joined === 'account/webhook/secret/regenerate') {
            $result = $this->client->request('account/webhook/secret/regenerate', 'POST', [], []);
            $this->persistWebhookSecret($result['data']);

            return $this->wrap($result, $upstream);
        }

        if ($method === 'POST' && FazerCardsAllowlist::isPaidPost($joined)) {
            return $this->wrap($this->paid($joined, $body), $upstream);
        }

        if ($method === 'POST' && FazerCardsAllowlist::isInvoicePost($joined)) {
            $result = $this->client->createPayment(
                (string) ($clean['method'] ?? ''),
                $clean['amount'] ?? 0,
                true,
            );

            return $this->wrap($result, $upstream);
        }

        if ($method === 'PATCH' && FazerCardsAllowlist::isGatedPatch($joined)) {
            $result = $this->client->patchSubscription($joined, $clean, true);

            return $this->wrap($result, $upstream);
        }

        if ($method === 'GET' && $joined === 'funpay/me') {
            return $this->wrap($this->client->funpayMe(), $upstream);
        }

        if ($method === 'GET' && $joined === 'orders') {
            return $this->wrap($this->client->listOrders($query), $upstream);
        }

        if ($method === 'GET' && ($parts[0] ?? '') === 'orders' && count($parts) === 2) {
            return $this->wrap($this->client->getSupplierOrder((string) $parts[1]), $upstream);
        }

        if ($method === 'GET' && $joined === 'subscription/activation-quote') {
            return $this->wrap($this->client->activationQuote(
                (string) ($query['plan'] ?? $clean['plan'] ?? ''),
                isset($query['promo_code']) ? (string) $query['promo_code'] : null,
            ), $upstream);
        }

        if ($method === 'POST' && count($parts) === 3 && ($parts[0] ?? '') === 'payments' && ($parts[2] ?? '') === 'verify') {
            return $this->wrap($this->client->verifyPayment(
                (string) ($parts[1] ?? ''),
                (string) ($clean['binanceOrderId'] ?? $clean['binance_order_id'] ?? ''),
            ), $upstream);
        }

        $result = $this->client->fetch(
            $joined,
            $method,
            $query,
            in_array($method, ['POST', 'PATCH', 'PUT'], true) ? $clean : null,
        );

        if ($joined === 'account/webhook' || $joined === 'account/webhook/secret/regenerate') {
            $this->persistWebhookSecret($result['data']);
        }

        return $this->wrap($result, $upstream);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    private function validate(array $body): array
    {
        $fields = is_array($body['fields'] ?? null)
            ? FazerCardsPayload::stringFields($body['fields'])
            : FazerCardsPayload::stringFields($body);
        $categoryId = (string) ($body['category_id'] ?? '');

        try {
            $result = $this->client->validatePlayerId($categoryId, $fields);
            $result['data'] = array_merge($result['data'], [
                'validation' => 'ok',
            ]);

            return $result;
        } catch (ApiException $e) {
            if ($e->errorCode !== 'ID_VALIDATION_UNSUPPORTED' && $e->errorCode !== 'VALIDATION_UNSUPPORTED') {
                throw $e;
            }

            return [
                'status' => 200,
                'latencyMs' => 0,
                'data' => [
                    'ok' => true,
                    'validation' => 'unsupported',
                    'warning' => $e->getMessage(),
                    'orderCategoryId' => $categoryId,
                ],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, latencyMs: int, data: array<string, mixed>}
     */
    private function paid(string $path, array $body): array
    {
        $opts = $body;
        $opts['confirm'] = true;

        return match ($path) {
            'topups/order' => $this->client->topupOrder([
                'category_id' => (string) ($body['category_id'] ?? ''),
                'offer_id' => (string) ($body['offer_id'] ?? ''),
                'fields' => is_array($body['fields'] ?? null) ? FazerCardsPayload::stringFields($body['fields']) : [],
                'confirm' => true,
                'priceUsd' => $body['priceUsd'] ?? $body['price_usd'] ?? null,
                'idempotencyKey' => $body['idempotencyKey'] ?? $body['idempotency_key'] ?? null,
            ]),
            'giftcards/order' => $this->client->giftcardOrder($opts),
            'gamekeys/order' => $this->client->gamekeyOrder($opts),
            'steam-topup/order' => $this->client->steamTopupOrder($opts),
            'steam-gifts/order' => $this->client->steamGiftOrder($this->stripGated($body), $opts),
            'telegram/stars/buy' => $this->client->telegramStarsBuy($opts),
            'telegram/premium/buy' => $this->client->telegramPremiumBuy($opts),
            'manual-services/order' => $this->client->manualServiceOrder($this->stripGated($body), $opts),
            'subscription/activate-from-balance' => $this->client->activateSubscription($opts),
            default => throw new ApiException('این مسیر FazerCards در allowlist نیست.', 404, 'UNKNOWN_ENDPOINT'),
        };
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function stripGated(array $body): array
    {
        unset($body['confirm'], $body['priceUsd'], $body['price_usd'], $body['idempotencyKey'], $body['idempotency_key']);

        return $body;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistWebhookSecret(array $data): void
    {
        $unwrapped = FazerCardsPayload::unwrap($data);
        $secret = $unwrapped['secret']
            ?? $data['secret']
            ?? (is_array($unwrapped['webhook'] ?? null) ? ($unwrapped['webhook']['secret'] ?? null) : null);
        if (! is_string($secret) || $secret === '' || str_starts_with($secret, '****')) {
            return;
        }
        $this->credentials->storeWebhookSecret($secret);
    }

    /**
     * @param  array{status: int, latencyMs: int, data: array<string, mixed>}  $result
     * @return array{status: int, latencyMs: int, upstreamPath: string, data: array<string, mixed>}
     */
    private function wrap(array $result, string $upstream): array
    {
        return [
            'status' => $result['status'],
            'latencyMs' => $result['latencyMs'],
            'upstreamPath' => $upstream,
            'data' => FazerCardsRedactor::scrub($result['data']),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function confirmed(array $body): bool
    {
        $value = $body['confirm'] ?? false;

        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function queryFlag(array $query, string $key): bool
    {
        $value = $query[$key] ?? null;

        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /**
     * @param  list<string>  $parts
     */
    private function isManualChat(array $parts): bool
    {
        return ($parts[0] ?? '') === 'manual-services'
            && ($parts[1] ?? '') === 'orders'
            && ($parts[3] ?? '') === 'chat'
            && count($parts) === 4;
    }
}
