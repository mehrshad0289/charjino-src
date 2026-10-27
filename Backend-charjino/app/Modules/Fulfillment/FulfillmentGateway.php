<?php

namespace App\Modules\Fulfillment;

interface FulfillmentGateway
{
    /**
     * @return array{playerName: string, region: string|null, zoneId: string|null}
     */
    public function validatePlayer(int $itemId, string $playerId, ?string $server = null, ?string $zoneId = null): array;

    /**
     * @return array{unitPrice: string, currency: string}
     */
    public function livePrice(int $itemId): array;

    /**
     * Live Shop2 create only. Caller must persist UUID in DB first.
     *
     * @param  array{order_id: string, sub_category_id: int, quantity: int, requirements: array<string, string>, expected_unit_price: string, confirm: bool}  $input
     * @return array{orderId: string, status: string}
     */
    public function createOrder(array $input): array;

    /**
     * @return array{orderId: string, status: string}
     */
    public function getOrder(string $orderId): array;

    public function isLiveCreateEnabled(): bool;
}
