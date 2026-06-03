<?php

namespace SaaSPlatform\Modules\Education\Contracts\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;

interface EducationLearningPathRepositoryInterface
{
    public function find(int $id): ?EducationLearningPath;
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;
    public function create(array $data): EducationLearningPath;
    public function update(EducationLearningPath $path, array $data): EducationLearningPath;
    public function delete(EducationLearningPath $path): void;
    public function forRole(string $role, ?int $siteId): Collection;
    public function forSite(?int $siteId): Collection;
}
