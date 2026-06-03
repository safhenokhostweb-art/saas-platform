<?php

namespace SaaSPlatform\Modules\Education\Services;

use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiContentGeneratorInterface;
use SaaSPlatform\Modules\Education\Models\EducationFeature;

class EducationAiContentGeneratorStub implements EducationAiContentGeneratorInterface
{
    public function generateBody(EducationFeature $feature): string
    {
        // Stub — returns empty string until an AI provider is wired.
        return '';
    }

    public function improveBody(string $existingBody, array $context = []): string
    {
        // Stub — returns the original body unchanged.
        return $existingBody;
    }

    public function isEnabled(): bool
    {
        return false;
    }
}
