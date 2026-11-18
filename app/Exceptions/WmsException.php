<?php

namespace App\Exceptions;

use RuntimeException;

class WmsException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly string $title, public readonly string $userMessage, public readonly int $status = 502, ?\Throwable $previous = null)
    {
        parent::__construct($errorCode, $status, $previous);
    }
}
