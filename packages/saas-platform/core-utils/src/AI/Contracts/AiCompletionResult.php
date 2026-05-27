<?php

namespace SaaSPlatform\CoreUtils\AI\Contracts;

class AiCompletionResult
{
    public function __construct(
        public readonly string  $text,
        public readonly bool    $success,
        public readonly ?string $error      = null,
        public readonly array   $meta       = [],
        public readonly float   $confidence = 1.0,
    ) {}

    public static function success(string $text, array $meta = [], float $confidence = 1.0): self
    {
        return new self(text: $text, success: true, meta: $meta, confidence: $confidence);
    }

    public static function failure(string $error): self
    {
        return new self(text: '', success: false, error: $error);
    }

    public function toArray(): array
    {
        return [
            'text'       => $this->text,
            'success'    => $this->success,
            'error'      => $this->error,
            'meta'       => $this->meta,
            'confidence' => $this->confidence,
        ];
    }
}
