<?php

namespace SaaSPlatform\Modules\Education\Exceptions;

use RuntimeException;

class InvalidGeneratedContentException extends RuntimeException
{
    /** @var string[] */
    private array $errors;

    public function __construct(array $errors, string $message = '')
    {
        $this->errors = $errors;
        parent::__construct($message ?: implode('; ', $errors));
    }

    /** @return string[] */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
