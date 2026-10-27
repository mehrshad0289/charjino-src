<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $orderId,
        public readonly string $from,
        public readonly string $to,
        public readonly string $actor,
        public readonly ?string $code = null,
    ) {}

    public function order(): ?Order
    {
        return Order::query()->find($this->orderId);
    }
}
