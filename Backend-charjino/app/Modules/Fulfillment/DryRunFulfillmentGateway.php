<?php

namespace App\Modules\Fulfillment;

final class DryRunFulfillmentGateway implements FulfillmentGateway
{
    public function validatePlayer(int $itemId, string $playerId, ?string $server = null, ?string $zoneId = null): array
    {
        $suffix = substr($playerId, -4);

        return [
            'playerName' => 'بازیکن آزمایشی '.$suffix,
            'region' => 'AS',
            'zoneId' => $zoneId,
        ];
    }

    public function livePrice(int $itemId): array
    {
        return [
            'unitPrice' => '0.100000',
            'currency' => 'USD',
        ];
    }

    public function createOrder(array $input): array
    {
        return [
            'orderId' => (string) $input['order_id'],
            'status' => 'pending',
        ];
    }

    public function getOrder(string $orderId): array
    {
        return [
            'orderId' => $orderId,
            'status' => 'pending',
        ];
    }

    public function isLiveCreateEnabled(): bool
    {
        return false;
    }
}
