<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Dto\EducationSearchQuery;

interface EducationSearchServiceInterface
{
    public function search(EducationSearchQuery $query): Collection;
    public function contextual(string $routeName, ?int $siteId, ?string $role): Collection;
}
