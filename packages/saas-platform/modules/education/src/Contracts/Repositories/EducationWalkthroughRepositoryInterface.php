<?php

namespace SaaSPlatform\Modules\Education\Contracts\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;

interface EducationWalkthroughRepositoryInterface
{
    public function find(int $id): ?EducationWalkthrough;
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;
    public function create(array $data): EducationWalkthrough;
    public function update(EducationWalkthrough $walkthrough, array $data): EducationWalkthrough;
    public function delete(EducationWalkthrough $walkthrough): void;
    public function forRoute(string $routeName, ?int $siteId): Collection;
    public function forSite(?int $siteId): Collection;
}
