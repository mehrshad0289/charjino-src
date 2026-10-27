<?php

namespace App\Modules\Supplier\Shop2;

use App\Exceptions\ApiException;
use App\Support\Money;

final class Shop2Adapter
{
    public function __construct(private readonly Shop2Client $client) {}

    /**
     * @return array{email: string, wallet: string, currency: string, active: bool}
     */
    public function account(): array
    {
        $row = $this->client->account();

        return [
            'email' => (string) ($row['email'] ?? ''),
            'wallet' => Money::string($row['wallet'] ?? '0'),
            'currency' => 'USD',
            'active' => (bool) ($row['active'] ?? true),
        ];
    }

    /**
     * @return array{unitPrice: string, currency: string}
     */
    public function livePrice(int $itemId): array
    {
        $row = $this->client->price($itemId);

        return [
            'unitPrice' => Money::string($row['unit_price'] ?? '0'),
            'currency' => 'USD',
        ];
    }

    /**
     * @return array{playerName: string, region: string|null, zoneId: string|null}
     */
    public function validatePlayer(int $itemId, string $playerId, ?string $server = null, ?string $zoneId = null): array
    {
        $playerId = trim($playerId);
        if ($playerId === '' || ForbiddenBuyerField::matches($playerId)) {
            throw new ApiException('شناسهٔ بازیکن نامعتبر است.', 400, 'PLAYER_NOT_FOUND');
        }
        if ($server !== null && ForbiddenBuyerField::matches($server)) {
            throw new ApiException('ورودی نامعتبر است.', 400, 'MISSING_REQUIRED_FIELD');
        }
        if ($zoneId !== null && ForbiddenBuyerField::matches($zoneId)) {
            throw new ApiException('ورودی نامعتبر است.', 400, 'MISSING_REQUIRED_FIELD');
        }

        $payload = $this->client->validatePlayer($itemId, array_filter([
            'player_id' => $playerId,
            'server' => $server,
            'zone_id' => $zoneId,
        ], fn ($value) => $value !== null && $value !== ''));

        $data = self::unwrap($payload);
        $name = (string) ($data['player_name'] ?? $data['playerName'] ?? $data['username'] ?? $data['nickname'] ?? '');

        return [
            'playerName' => $name,
            'region' => self::stringOrNull($data['region'] ?? $data['server'] ?? null),
            'zoneId' => self::stringOrNull($data['zone_id'] ?? $data['zoneId'] ?? null),
        ];
    }

    /**
     * @return array{orderId: string, status: string}
     */
    public function getOrder(string $orderId): array
    {
        $payload = $this->client->getOrder($orderId);
        $data = self::unwrap($payload);
        $order = is_array($data['order'] ?? null) ? $data['order'] : $data;

        return [
            'orderId' => (string) ($order['order_id'] ?? $order['id'] ?? $orderId),
            'status' => (string) ($order['status'] ?? 'pending'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function unwrap(array $payload): array
    {
        $data = $payload['data'] ?? $payload;
        if (is_array($data['data'] ?? null)) {
            $data = $data['data'];
        }

        return is_array($data) ? $data : [];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}
