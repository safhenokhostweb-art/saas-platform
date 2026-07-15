<?php

namespace SaaSPlatform\Modules\MotionGraphics\Data;

final readonly class MotionAiRequest
{
    public function __construct(
        public string $prompt,
        public array $sourceDefinition,
        public array $editableSchema,
        public array $metadata = [],
        public array $options = [],
    ) {}
}
