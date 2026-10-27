<?php

namespace App\Modules\Supplier\Shop2;

final class ForbiddenBuyerField
{
    public static function matches(string $name): bool
    {
        return (bool) preg_match('/pass|otp|pin|login/i', $name);
    }
}
