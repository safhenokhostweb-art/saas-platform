<?php

namespace SaaSPlatform\Modules\MotionGraphics\Data;

final readonly class MotionAiResult
{
    public function __construct(
        public bool $success,
        public array $sourceDefinition = [],
        public array $responseMetadata = [],
        public ?string $error = null,
    ) {}

    public static function success(array $sourceDefinition, array $metadata = []): self
    {
        return new self(true, $sourceDefinition, $metadata);
    }

    public static function failure(string $error, array $metadata = []): self
    {
        return new self(false, [], $metadata, $error);
    }
}
