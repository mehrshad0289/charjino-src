<?php

namespace App\Exceptions;

class IllegalTransition extends ApiException
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct('تغییر وضعیت سفارش مجاز نیست.', 400, 'ORDER_STATE_INVALID');
    }
}
