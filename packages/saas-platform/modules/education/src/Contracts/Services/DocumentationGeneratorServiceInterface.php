<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;

interface DocumentationGeneratorServiceInterface
{
    /** Generate draft articles for all features that have no documentation. Returns count created. */
    public function generateMissing(?int $siteId = null): int;

    /** Generate a draft article for a single feature. Returns null if documentation already exists. */
    public function generateForFeature(EducationFeature $feature): ?EducationArticle;

    /** Regenerate auto-generated article for a feature (overwrites only auto_generated=true articles). */
    public function regenerateForFeature(EducationFeature $feature): EducationArticle;
}
