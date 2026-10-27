<?php

namespace App\Modules\Fulfillment;

use Illuminate\Support\Str;

final class FazerIdempotencyKey
{
    public static function make(string $orderNumber, int|string $itemId, int $attemptNo): string
    {
        $key = 'cj-'.$orderNumber.'-'.$itemId.'-a'.$attemptNo.'-'.Str::uuid();

        return mb_substr($key, 0, 255);
    }
}
