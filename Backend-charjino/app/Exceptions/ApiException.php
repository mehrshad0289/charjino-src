<?php

namespace App\Exceptions;

use RuntimeException;

class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly ?string $errorCode = null,
        public readonly ?int $retryAfter = null,
        public readonly mixed $data = null,
    ) {
        parent::__construct($message, $status);
    }
}
