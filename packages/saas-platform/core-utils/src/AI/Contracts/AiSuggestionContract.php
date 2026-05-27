<?php

namespace SaaSPlatform\CoreUtils\AI\Contracts;

interface AiSuggestionContract
{
    public function getSuggestions(array $context): array;

    public function getSuggestionType(): string;

    public function getConfidenceThreshold(): float;
}
