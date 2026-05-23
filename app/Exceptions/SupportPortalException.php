<?php

namespace App\Exceptions;

use RuntimeException;

class SupportPortalException extends RuntimeException
{
    public function __construct(
        private readonly string $publicError,
        string $publicMessage,
        private readonly int $httpStatus = 403
    ) {
        parent::__construct($publicMessage);
    }

    public function publicError(): string
    {
        return $this->publicError;
    }

    public function publicMessage(): string
    {
        return $this->getMessage();
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
