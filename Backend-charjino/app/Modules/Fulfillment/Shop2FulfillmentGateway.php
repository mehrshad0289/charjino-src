<?php

namespace App\Modules\Fulfillment;

use App\Modules\Supplier\Shop2\Shop2Adapter;
use App\Modules\Supplier\Shop2\Shop2Client;

final class Shop2FulfillmentGateway implements FulfillmentGateway
{
    public function __construct(
        private readonly Shop2Adapter $adapter,
        private readonly Shop2Client $client,
    ) {}

    public function validatePlayer(int $itemId, string $playerId, ?string $server = null, ?string $zoneId = null): array
    {
        return $this->adapter->validatePlayer($itemId, $playerId, $server, $zoneId);
    }

    public function livePrice(int $itemId): array
    {
        return $this->adapter->livePrice($itemId);
    }

    public function createOrder(array $input): array
    {
        $result = $this->client->createOrder($input);
        $data = $result['data'] ?? [];
        if (is_array($data['data'] ?? null)) {
            $data = $data['data'];
        }
        $order = is_array($data['order'] ?? null) ? $data['order'] : $data;

        return [
            'orderId' => (string) ($order['order_id'] ?? $order['id'] ?? $input['order_id']),
            'status' => (string) ($order['status'] ?? 'pending'),
        ];
    }

    public function getOrder(string $orderId): array
    {
        return $this->adapter->getOrder($orderId);
    }

    public function isLiveCreateEnabled(): bool
    {
        return $this->client->liveOrderEnabled() && $this->client->configured();
    }
}
