<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Support\Collection;

interface EducationContextServiceInterface
{
    public function recommendForCurrentRoute(?int $siteId, ?string $role): Collection;
    public function walkthroughsForCurrentRoute(?int $siteId, ?string $role): Collection;
    public function learningPathsForRole(string $role, ?int $siteId): Collection;
}
