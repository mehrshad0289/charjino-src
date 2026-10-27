<?php

namespace App\Modules\Fulfillment;

use App\Exceptions\ApiException;
use App\Modules\Supplier\Shop2\Shop2Adapter;
use App\Modules\Supplier\Shop2\Shop2Client;
use App\Support\CustomerError;

final class CharjinoFulfillmentGateway implements FulfillmentGateway
{
    public function __construct(
        private readonly Shop2Adapter $adapter,
        private readonly Shop2Client $client,
        private readonly Shop2FulfillmentGateway $live,
        private readonly DryRunFulfillmentGateway $dryRun,
    ) {}

    public function validatePlayer(int $itemId, string $playerId, ?string $server = null, ?string $zoneId = null): array
    {
        if (! $this->client->configured()) {
            return $this->dryRun->validatePlayer($itemId, $playerId, $server, $zoneId);
        }

        try {
            return $this->adapter->validatePlayer($itemId, $playerId, $server, $zoneId);
        } catch (ApiException $e) {
            throw new ApiException(CustomerError::message($e->errorCode, $e->getMessage()), $e->status, $e->errorCode);
        }
    }

    public function livePrice(int $itemId): array
    {
        if (! $this->client->configured()) {
            return $this->dryRun->livePrice($itemId);
        }

        return $this->adapter->livePrice($itemId);
    }

    public function createOrder(array $input): array
    {
        return $this->live->createOrder($input);
    }

    public function getOrder(string $orderId): array
    {
        if ($this->isLiveCreateEnabled()) {
            return $this->adapter->getOrder($orderId);
        }

        return $this->dryRun->getOrder($orderId);
    }

    public function isLiveCreateEnabled(): bool
    {
        return $this->live->isLiveCreateEnabled();
    }
}
