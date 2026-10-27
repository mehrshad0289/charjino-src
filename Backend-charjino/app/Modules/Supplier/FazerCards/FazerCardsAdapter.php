<?php

namespace App\Modules\Supplier\FazerCards;

use App\Exceptions\ApiException;
use App\Models\Sku;
use App\Support\Money;

final class FazerCardsAdapter
{
    public function __construct(private readonly FazerCardsClient $client) {}

    public function configured(): bool
    {
        return $this->client->configured();
    }

    public function isLiveCreateEnabled(): bool
    {
        return $this->client->configured() && $this->client->liveOrderEnabled();
    }

    /**
     * @return array{email: string, login: string, plan: string, planExpiresAt: string, planAutoRenew: bool, wallet: string, currency: string, active: bool}
     */
    public function account(): array
    {
        $me = FazerCardsPayload::unwrap($this->client->fetch('me', 'GET', [], null, 4)['data']);
        $balance = FazerCardsPayload::unwrap($this->client->fetch('balance', 'GET', [], null, 4)['data']);
        $sub = [];
        try {
            $sub = FazerCardsPayload::unwrap($this->client->fetch('subscription', 'GET', [], null, 4)['data']);
        } catch (\Throwable) {
        }

        return [
            'email' => (string) ($me['email'] ?? ''),
            'login' => (string) ($me['login'] ?? ''),
            'plan' => (string) ($sub['plan'] ?? $me['plan'] ?? ''),
            'planExpiresAt' => (string) ($sub['planExpiresAt'] ?? $me['planExpiresAt'] ?? ''),
            'planAutoRenew' => (bool) ($sub['planAutoRenew'] ?? $me['planAutoRenew'] ?? false),
            'wallet' => Money::string($balance['balance'] ?? '0'),
            'currency' => (string) ($sub['currency'] ?? $balance['currency'] ?? 'USD'),
            'active' => (bool) ($sub['subscriptionActive'] ?? $me['subscriptionActive'] ?? false),
        ];
    }

    /**
     * @param  array<string, string>  $requirements
     * @return array{playerName: string, region: string|null, zoneId: string|null, validateMode: string, valid: bool}
     */
    public function validatePlayer(Sku $sku, array $requirements): array
    {
        $family = FazerCardsIds::familyOf($sku->fulfillment_type, $sku->fazercards_category_id);
        if ($family === FazerCardsIds::STEAM_TOPUP) {
            $login = trim((string) ($requirements['steamLogin'] ?? $requirements['player_id'] ?? ''));
            if ($login === '') {
                throw new ApiException('نام کاربری ورود استیم را وارد کن.', 400, 'MISSING_REQUIRED_FIELD');
            }

            return $this->validateSteamLogin($login);
        }

        if ($family !== FazerCardsIds::TOPUP) {
            throw new ApiException('اعتبارسنجی شناسه برای این محصول از سمت تأمین‌کننده پشتیبانی نمی‌شود.', 400, 'ID_VALIDATION_UNSUPPORTED');
        }

        $categoryId = FazerCardsIds::rawCategory($sku->fazercards_category_id);
        if ($categoryId === '') {
            throw new ApiException('شناسهٔ بازیکن نامعتبر است.', 400, 'PLAYER_NOT_FOUND');
        }

        $fields = $requirements;
        unset($fields['currency'], $fields['amount']);
        try {
            $result = $this->client->validatePlayerId($categoryId, $fields);
        } catch (ApiException $e) {
            if ($e->errorCode === 'ID_VALIDATION_UNSUPPORTED') {
                throw $e;
            }
            if ($e->status === 422) {
                throw new ApiException(\App\Support\CustomerError::message('ID_NOT_CONFIRMED'), 422, 'ID_NOT_CONFIRMED');
            }
            throw $e;
        }
        $data = FazerCardsPayload::unwrap($result['data']);
        $valid = ($data['valid'] ?? null) === true;
        if (! $valid) {
            throw new ApiException('شناسهٔ بازیکن پیدا نشد.', 400, 'PLAYER_NOT_FOUND');
        }
        $region = $this->stringOrNull($data['region'] ?? $data['server'] ?? null);
        $this->assertRegionAllowed($sku, $region);
        $display = trim((string) ($fields['player_id'] ?? $fields['riot_id'] ?? $fields['user_id'] ?? $fields['uid'] ?? ''));

        return [
            'playerName' => (string) ($data['player_name'] ?? $data['playerName'] ?? $data['username'] ?? $display),
            'region' => $region,
            'zoneId' => $this->stringOrNull($data['zone_id'] ?? $data['zoneId'] ?? ($requirements['server_id'] ?? $requirements['zone_id'] ?? null)),
            'validateMode' => 'api',
            'valid' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function steamRatesPayload(): array
    {
        return $this->client->fetch('steam-topup/rates')['data'];
    }

    /**
     * @param  array<string, string>  $requirements
     * @return array{orderId: string, status: string}
     */
    public function placeOrder(Sku $sku, array $requirements, string $priceUsd, string $idempotency, bool $confirm = true, int $quantity = 1): array
    {
        $family = FazerCardsIds::familyOf($sku->fulfillment_type, $sku->fazercards_category_id);
        $quantity = max(1, $quantity);
        $opts = [
            'confirm' => $confirm,
            'priceUsd' => $priceUsd,
            'idempotencyKey' => $idempotency,
        ];
        $result = match ($family) {
            FazerCardsIds::GIFTCARD => $this->client->giftcardOrder([
                'category_id' => FazerCardsIds::rawCategory($sku->fazercards_category_id),
                'card_id' => (string) $sku->fazercards_offer_id,
                'quantity' => $quantity,
                ...$opts,
            ]),
            FazerCardsIds::GAMEKEY => $this->client->gamekeyOrder([
                'game_id' => FazerCardsIds::rawCategory($sku->fazercards_category_id),
                'key_id' => (string) $sku->fazercards_offer_id,
                'quantity' => $quantity,
                ...$opts,
            ]),
            FazerCardsIds::STEAM_TOPUP => $this->client->steamTopupOrder([
                'steamLogin' => (string) ($requirements['steamLogin'] ?? $requirements['player_id'] ?? ''),
                'currency' => strtoupper(trim((string) ($requirements['currency'] ?? ''))),
                'amount' => (string) ($requirements['amount'] ?? ''),
                ...$opts,
            ]),
            FazerCardsIds::STEAM_GIFT => $this->client->steamGiftOrder([
                'invite_url' => $this->steamGiftInvite($requirements),
                'sub_id' => $this->steamGiftSubId($sku),
                'app_id' => $this->steamAppId($sku),
                'region' => $this->steamGiftRegion($sku),
            ], $opts),
            FazerCardsIds::TELEGRAM_STARS => $this->client->telegramStarsBuy([
                'telegram_username' => $this->telegramUsername($requirements),
                'quantity' => (int) $sku->fazercards_offer_id,
                ...$opts,
            ]),
            FazerCardsIds::TELEGRAM_PREMIUM => $this->client->telegramPremiumBuy([
                'telegram_username' => $this->telegramUsername($requirements),
                'months' => (int) $sku->fazercards_offer_id,
                ...$opts,
            ]),
            FazerCardsIds::MANUAL => $this->client->manualServiceOrder([
                'manual_service_id' => FazerCardsIds::rawCategory($sku->fazercards_category_id),
                'product_id' => (string) $sku->fazercards_offer_id,
                'fields' => $this->manualOrderFields($sku, $requirements),
            ], $opts),
            default => $this->client->topupOrder([
                'category_id' => FazerCardsIds::rawCategory($sku->fazercards_category_id),
                'offer_id' => (string) $sku->fazercards_offer_id,
                'fields' => $this->topupOrderFields($sku, $requirements),
                ...$opts,
            ]),
        };

        return $this->unwrapOrder($result['data'], $idempotency);
    }

    /**
     * @return array{orderId: string, status: string, cards: list<string>, keys: list<string>, failReason: string|null, raw: array<string, mixed>}
     */
    public function placeFromItem(\App\Models\OrderItem $item, string $priceUsd, string $idempotency, bool $confirm = true): array
    {
        $sku = $item->sku;
        if (! $sku) {
            throw new ApiException('این بسته برای فروش فعال نیست.', 400, 'SKU_UNAVAILABLE');
        }
        $ref = is_array($item->supplier_ref) ? $item->supplier_ref : [];
        if (isset($ref['category_id'])) {
            $sku->fazercards_category_id = (string) $ref['category_id'];
        }
        $offer = $ref['offer_id'] ?? $ref['card_id'] ?? $ref['key_id'] ?? $ref['stars_quantity'] ?? $ref['months'] ?? null;
        if ($offer !== null && $offer !== '') {
            $sku->fazercards_offer_id = (string) $offer;
        }
        if (isset($ref['region']) && $ref['region'] !== '') {
            $sku->region = (string) $ref['region'];
        }
        $requirements = is_array($item->requirements) ? $item->requirements : [];
        if ($item->steam_currency) {
            $requirements['currency'] = (string) $item->steam_currency;
        }
        if ($item->steam_amount) {
            $requirements['amount'] = (string) $item->steam_amount;
        }

        return $this->placeOrder($sku, $requirements, $priceUsd, $idempotency, $confirm, max(1, (int) $item->quantity));
    }

    /**
     * @return array{unitPrice: string, currency: string}
     */
    public function livePrice(Sku $sku): array
    {
        $family = FazerCardsIds::familyOf($sku->fulfillment_type, $sku->fazercards_category_id);
        $offerId = trim((string) $sku->fazercards_offer_id);
        $categoryId = FazerCardsIds::rawCategory($sku->fazercards_category_id);
        if ($offerId === '' || $categoryId === '') {
            throw new ApiException('این بسته به FazerCards وصل نیست.', 400, 'PRODUCT_UNAVAILABLE');
        }

        $usd = match ($family) {
            FazerCardsIds::GIFTCARD => $this->findUsd(
                $this->client->fetch('giftcards/cards', 'GET', ['category_id' => $categoryId, 'include_ui' => '1'])['data'],
                ['offers', 'cards', 'items'],
                $offerId,
                ['card_id', 'offer_id', 'id'],
            ),
            FazerCardsIds::GAMEKEY => $this->findUsd(
                $this->client->fetch('gamekeys/keys', 'GET', ['game_id' => $categoryId, 'include_ui' => '1'])['data'],
                ['keys', 'items', 'offers'],
                $offerId,
                ['key_id', 'offer_id', 'id'],
            ),
            FazerCardsIds::TELEGRAM_STARS => $this->telegramStarUsd($offerId),
            FazerCardsIds::TELEGRAM_PREMIUM => $this->usdFromRows(
                FazerCardsTelegramQuotes::premiumOffers($this->client->fetch('telegram/premium')['data']),
                $offerId,
                ['months', 'offer_id', 'id'],
            ),
            FazerCardsIds::STEAM_TOPUP => $this->findSteamRateUsd($sku),
            FazerCardsIds::STEAM_GIFT => $this->steamGiftUsd($categoryId, $offerId),
            FazerCardsIds::MANUAL => $this->findUsd(
                $this->client->fetch('manual-services/'.$categoryId.'/offers', 'GET', ['include_ui' => '1'])['data'],
                ['items', 'offers'],
                $offerId,
                ['id', 'product_id', 'offer_id'],
            ),
            default => $this->findUsd(
                $this->client->fetch('topups/offers', 'GET', ['category_id' => $categoryId, 'include_ui' => '1'])['data'],
                ['offers', 'items'],
                $offerId,
                ['offer_id', 'id'],
            ),
        };

        return [
            'unitPrice' => $usd,
            'currency' => 'USD',
        ];
    }

    /**
     * @param  array{category_id: string, offer_id: string, fields: array<string, string>, confirm: bool, priceUsd?: string, idempotencyKey?: string}  $input
     * @return array{orderId: string, status: string}
     */
    public function createTopupOrder(array $input): array
    {
        $result = $this->client->topupOrder($input);

        return $this->unwrapOrder($result['data'], (string) ($input['idempotencyKey'] ?? ''));
    }

    /**
     * @return array{orderId: string, status: string, cards: list<string>, keys: list<string>, failReason: string|null, raw: array<string, mixed>}
     */
    public function getOrder(string $orderId): array
    {
        $result = $this->client->fetch('orders/'.$orderId);

        return FazerOrderParser::parse($result['data'], $orderId);
    }

    /**
     * @return array{playerName: string, region: string|null, zoneId: string|null, validateMode: string, valid: bool}
     */
    private function validateSteamLogin(string $login): array
    {
        $result = $this->client->checkSteamLogin($login);
        $data = FazerCardsPayload::unwrap($result['data']);
        $unverified = FazerCardsSteamTopupQuotes::flag($data['unverified'] ?? false);
        if (! FazerCardsSteamTopupQuotes::loginAllowed($result['data'])) {
            throw new ApiException(\App\Support\CustomerError::message('STEAM_LOGIN_CANNOT_REFILL'), 400, 'STEAM_LOGIN_CANNOT_REFILL');
        }

        return [
            'playerName' => (string) ($data['persona'] ?? $data['player_name'] ?? $data['username'] ?? $login),
            'region' => $this->stringOrNull($data['region'] ?? null),
            'zoneId' => null,
            'validateMode' => 'api',
            'valid' => true,
            'unverified' => $unverified,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $listKeys
     * @param  list<string>  $idKeys
     */
    private function findUsd(array $payload, array $listKeys, string $offerId, array $idKeys): string
    {
        return $this->usdFromRows(FazerCardsPayload::listFrom($payload, $listKeys), $offerId, $idKeys);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $idKeys
     */
    private function usdFromRows(array $rows, string $offerId, array $idKeys): string
    {
        foreach ($rows as $row) {
            foreach ($idKeys as $key) {
                if ((string) ($row[$key] ?? '') === $offerId) {
                    $usd = Money::string($row['price_usd'] ?? $row['usd'] ?? $row['cost_usd'] ?? $row['price'] ?? '0');
                    if (Money::isPositive($usd)) {
                        return $usd;
                    }
                }
            }
        }

        throw new ApiException('این بسته فعلاً موجود نیست.', 400, 'OUT_OF_STOCK');
    }

    private function findSteamRateUsd(Sku $sku): string
    {
        $payload = $this->client->fetch('steam-topup/rates')['data'];
        $usd = FazerCardsSteamTopupQuotes::usdForOffer($payload, (string) $sku->fazercards_offer_id);
        if (Money::isPositive($usd)) {
            return $usd;
        }

        throw new ApiException('این بسته فعلاً موجود نیست.', 400, 'OUT_OF_STOCK');
    }

    private function telegramStarUsd(string $offerId): string
    {
        $usd = FazerCardsTelegramQuotes::starUsd(
            $this->client->fetch('telegram/stars')['data'],
            (int) $offerId,
        );
        if (Money::isPositive($usd)) {
            return $usd;
        }

        throw new ApiException('این بسته فعلاً موجود نیست.', 400, 'OUT_OF_STOCK');
    }

    /**
     * GET /topups/offers فیلدها را جدا می‌دهد؛ فروشگاه player_id را نرمال می‌کند.
     *
     * @param  array<string, string>  $requirements
     * @return array<string, string>
     */
    private function topupOrderFields(Sku $sku, array $requirements): array
    {
        $sku->loadMissing('product');
        $schema = is_array($sku->product?->purchase_schema) ? $sku->product->purchase_schema : [];
        $keys = [];
        foreach (is_array($schema['fields'] ?? null) ? $schema['fields'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? $row['name'] ?? ''));
            if ($key !== '') {
                $keys[] = $key;
            }
        }
        if ($keys === []) {
            $inputs = is_array($sku->product?->inputs) ? $sku->product->inputs : [];
            foreach ($inputs as $row) {
                $key = is_string($row) ? trim($row) : trim((string) ($row['key'] ?? $row['name'] ?? ''));
                if ($key === 'playerId') {
                    $key = 'player_id';
                }
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }
        $fields = [];
        foreach ($keys !== [] ? $keys : array_keys($requirements) as $key) {
            if (in_array($key, ['currency', 'amount', 'id_ack', 'idAck', 'id_ack_at', 'version', 'region_ack', 'id_unchecked', 'steam_unverified'], true)) {
                continue;
            }
            if (isset($requirements[$key]) && is_scalar($requirements[$key]) && trim((string) $requirements[$key]) !== '') {
                $fields[$key] = trim((string) $requirements[$key]);
            }
        }
        if ($fields === [] && trim((string) ($requirements['player_id'] ?? '')) !== '') {
            $fields['player_id'] = trim((string) $requirements['player_id']);
        }

        return $fields;
    }

    /**
     * GET /manual-services/{id}/offers فیلدها را با code می‌دهد.
     *
     * @param  array<string, string>  $requirements
     * @return array<string, string>
     */
    private function manualOrderFields(Sku $sku, array $requirements): array
    {
        $sku->loadMissing('product');
        $keys = [];
        $inputs = is_array($sku->product?->inputs) ? $sku->product->inputs : [];
        foreach ($inputs as $row) {
            $key = '';
            if (is_string($row)) {
                $key = trim($row);
            } elseif (is_array($row)) {
                $key = trim((string) ($row['name'] ?? $row['code'] ?? $row['key'] ?? ''));
            }
            if ($key === 'playerId') {
                $key = 'player_id';
            }
            if ($key !== '') {
                $keys[] = $key;
            }
        }
        $keys = array_values(array_unique($keys));
        if ($keys === []) {
            return $requirements !== [] ? $requirements : [];
        }

        $fields = FazerCardsValidateId::pickFields($keys, $requirements);
        if ($fields === [] && trim((string) ($requirements['player_id'] ?? '')) !== '') {
            $fields['player_id'] = trim((string) $requirements['player_id']);
        }

        return $fields;
    }

    /**
     * @param  array<string, string>  $requirements
     */
    private function telegramUsername(array $requirements): string
    {
        $user = ltrim(trim((string) ($requirements['telegram_username'] ?? $requirements['player_id'] ?? '')), '@');
        if ($user === '') {
            throw new ApiException('نام کاربری تلگرام را وارد کن.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $user;
    }

    private function steamAppId(Sku $sku): mixed
    {
        $raw = FazerCardsIds::rawCategory($sku->fazercards_category_id);

        return ctype_digit($raw) ? (int) $raw : $raw;
    }

    private function steamGiftSubId(Sku $sku): int
    {
        [$subId] = FazerCardsSteamGiftQuotes::splitOfferId((string) $sku->fazercards_offer_id);

        return (int) $subId;
    }

    private function steamGiftRegion(Sku $sku): string
    {
        $stored = strtoupper(trim((string) $sku->region));
        if ($stored !== '') {
            return $stored;
        }
        [, $region] = FazerCardsSteamGiftQuotes::splitOfferId((string) $sku->fazercards_offer_id);

        if ($region === '') {
            throw new ApiException('ریجن این گیفت استیم مشخص نیست.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $region;
    }

    /**
     * @param  array<string, string>  $requirements
     */
    private function steamGiftInvite(array $requirements): string
    {
        $url = FazerCardsSteamGiftQuotes::normalizeInviteUrl((string) ($requirements['invite_url'] ?? $requirements['player_id'] ?? ''));
        if (! FazerCardsSteamGiftQuotes::inviteAllowed($url)) {
            throw new ApiException('لینک دعوت استیم نامعتبر است.', 400, 'MISSING_REQUIRED_FIELD');
        }

        return $url;
    }

    private function steamGiftUsd(string $appId, string $offerId): string
    {
        $usd = FazerCardsSteamGiftQuotes::usdForOffer(
            $this->client->fetch('steam-gifts/games/'.$appId)['data'],
            $offerId,
        );
        if (! Money::isPositive($usd)) {
            throw new ApiException('این بسته فعلاً موجود نیست.', 400, 'OUT_OF_STOCK');
        }

        return $usd;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{orderId: string, status: string, cards: list<string>, keys: list<string>, failReason: string|null, raw: array<string, mixed>}
     */
    private function unwrapOrder(array $payload, string $fallback): array
    {
        return FazerOrderParser::parse($payload, $fallback);
    }

    private function assertRegionAllowed(Sku $sku, ?string $region): void
    {
        $region = strtoupper(trim((string) $region));
        if ($region === '') {
            return;
        }
        $sku->loadMissing('product');
        $hay = mb_strtolower(trim((string) $sku->product?->title.' '.$sku->product?->name_en.' '.$sku->product?->handle));
        if (preg_match('/mobile legends|\bmlbb\b/', $hay) === 1 && in_array($region, ['ID', 'BR'], true)) {
            throw new ApiException(\App\Support\CustomerError::message('REGION_MISMATCH'), 400, 'REGION_MISMATCH');
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}
