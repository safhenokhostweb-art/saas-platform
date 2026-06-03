<?php

namespace SaaSPlatform\Modules\Education\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationLearningPathRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;

class EducationLearningPathRepository implements EducationLearningPathRepositoryInterface
{
    public function find(int $id): ?EducationLearningPath
    {
        return EducationLearningPath::with('steps')->find($id);
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return EducationLearningPath::with('steps')
            ->when(! empty($filters['site_id']), fn($q) => $q->forSite($filters['site_id']))
            ->when(! empty($filters['role']), fn($q) => $q->forRole($filters['role']))
            ->when(isset($filters['is_active']), fn($q) => $q->where('is_active', $filters['is_active']))
            ->orderBy('sort_order')
            ->paginate($perPage);
    }

    public function create(array $data): EducationLearningPath
    {
        return EducationLearningPath::create($data);
    }

    public function update(EducationLearningPath $path, array $data): EducationLearningPath
    {
        $path->update($data);
        return $path->fresh('steps');
    }

    public function delete(EducationLearningPath $path): void
    {
        $path->delete();
    }

    public function forRole(string $role, ?int $siteId): Collection
    {
        return EducationLearningPath::active()
            ->forRole($role)
            ->when($siteId !== null, fn($q) => $q->forSite($siteId))
            ->with('steps')
            ->orderBy('sort_order')
            ->get();
    }

    public function forSite(?int $siteId): Collection
    {
        return EducationLearningPath::active()
            ->when($siteId !== null, fn($q) => $q->forSite($siteId))
            ->with('steps')
            ->orderBy('sort_order')
            ->get();
    }
}
