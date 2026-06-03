<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use SaaSPlatform\Modules\Education\Models\EducationFeature;

/**
 * Future AI content generation contract.
 * Bind to EducationAiContentGeneratorStub until an AI provider is configured.
 */
interface EducationAiContentGeneratorInterface
{
    /** Generate a documentation body for a feature. Returns raw HTML string. */
    public function generateBody(EducationFeature $feature): string;

    /** Improve an existing article body. Returns improved HTML string. */
    public function improveBody(string $existingBody, array $context = []): string;

    /** Returns true when an AI provider is actually configured and enabled. */
    public function isEnabled(): bool;
}
