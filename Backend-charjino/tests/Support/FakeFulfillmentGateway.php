<?php

namespace Tests\Support;

use App\Exceptions\ApiException;
use App\Models\FulfillmentAttempt;
use App\Modules\Fulfillment\FulfillmentGateway;

final class FakeFulfillmentGateway implements FulfillmentGateway
{
    public bool $live = true;

    public int $createCalls = 0;

    public int $getOrderCalls = 0;

    public int $priceCalls = 0;

    /** @var list<string> */
    public array $createIds = [];

    /** @var list<string|null> */
    public array $createErrors = [];

    /** @var list<string> */
    public array $prices = ['0.100000'];

    public string $createStatus = 'pending';

    public string $getStatus = 'pending';

    public string $playerName = 'TestPlayer';

    public ?string $lastExpectedPrice = null;

    public function validatePlayer(int $itemId, string $playerId, ?string $server = null, ?string $zoneId = null): array
    {
        return [
            'playerName' => $this->playerName,
            'region' => 'AS',
            'zoneId' => $zoneId,
        ];
    }

    public function livePrice(int $itemId): array
    {
        $index = min($this->priceCalls, count($this->prices) - 1);
        $this->priceCalls++;

        return [
            'unitPrice' => $this->prices[$index],
            'currency' => 'USD',
        ];
    }

    public function createOrder(array $input): array
    {
        $uuid = (string) $input['order_id'];
        $this->assertUuidAlreadyPersisted($uuid);
        $this->createCalls++;
        $this->createIds[] = $uuid;
        $this->lastExpectedPrice = (string) ($input['expected_unit_price'] ?? '');

        $error = $this->createErrors[$this->createCalls - 1] ?? null;
        if (is_string($error) && $error !== '') {
            $status = match ($error) {
                'DUPLICATE_ORDER' => 409,
                'PRICE_INCREASED' => 400,
                'ORDER_NOT_FOUND' => 404,
                'SUPPLIER_UNREACHABLE' => 503,
                default => 400,
            };
            throw new ApiException($error, $status, $error);
        }

        return [
            'orderId' => $uuid,
            'status' => $this->createStatus,
        ];
    }

    public function getOrder(string $orderId): array
    {
        $this->getOrderCalls++;

        return [
            'orderId' => $orderId,
            'status' => $this->getStatus,
        ];
    }

    public function isLiveCreateEnabled(): bool
    {
        return $this->live;
    }

    private function assertUuidAlreadyPersisted(string $uuid): void
    {
        if (! FulfillmentAttempt::query()->where('supplier_order_id', $uuid)->exists()) {
            throw new ApiException('UUID was not persisted before create.', 500, 'UUID_NOT_PERSISTED');
        }
    }
}
