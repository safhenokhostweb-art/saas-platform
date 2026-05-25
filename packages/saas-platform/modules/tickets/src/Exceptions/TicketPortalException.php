<?php

namespace SaaSPlatform\Modules\Tickets\Exceptions;

use RuntimeException;

class TicketPortalException extends RuntimeException
{
    /**
     * @param string     $publicError   Machine-readable error code
     * @param string     $publicMessage Human-readable Persian error message
     * @param int        $httpStatus    HTTP status code (default 403)
     */
    public function __construct(
        private readonly string $publicError,
        string $publicMessage,
        private readonly int $httpStatus = 403
    ) {
        parent::__construct($publicMessage);
    }

    /**
     * Get the machine-readable error code.
     */
    public function publicError(): string
    {
        return $this->publicError;
    }

    /**
     * Get the human-readable error message.
     */
    public function publicMessage(): string
    {
        return $this->getMessage();
    }

    /**
     * Get the HTTP status code.
     */
    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
