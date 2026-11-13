<?php

namespace Webkul\Churn\Exceptions;

use RuntimeException;

class ChurnApiException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly ?int $httpStatus = null, public readonly int $attempts = 0)
    {
        // Do not chain transport exceptions: they can contain credentials or customer payloads.
        parent::__construct('API ML: '.$reason);
    }
}
