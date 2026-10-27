<?php

namespace App\Modules\Payments;

final class PaymentProviders
{
    /**
     * @return list<string>
     */
    public static function shopPaid(): array
    {
        return ['mock', 'zibal', 'zarinpal', 'wallet'];
    }
}
