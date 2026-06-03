<?php

namespace SaaSPlatform\Modules\Education\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationWalkthroughRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;

class EducationWalkthroughRepository implements EducationWalkthroughRepositoryInterface
{
    public function find(int $id): ?EducationWalkthrough
    {
        return EducationWalkthrough::with('steps.media')->find($id);
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return EducationWalkthrough::with('steps')
            ->when(! empty($filters['site_id']), fn($q) => $q->forSite($filters['site_id']))
            ->when(! empty($filters['module_slug']), fn($q) => $q->where('module_slug', $filters['module_slug']))
            ->when(isset($filters['is_active']), fn($q) => $q->where('is_active', $filters['is_active']))
            ->orderBy('sort_order')
            ->paginate($perPage);
    }

    public function create(array $data): EducationWalkthrough
    {
        return EducationWalkthrough::create($data);
    }

    public function update(EducationWalkthrough $walkthrough, array $data): EducationWalkthrough
    {
        $walkthrough->update($data);
        return $walkthrough->fresh('steps');
    }

    public function delete(EducationWalkthrough $walkthrough): void
    {
        $walkthrough->delete();
    }

    public function forRoute(string $routeName, ?int $siteId): Collection
    {
        return EducationWalkthrough::active()
            ->forRoute($routeName)
            ->when($siteId !== null, fn($q) => $q->forSite($siteId))
            ->with('steps')
            ->orderBy('sort_order')
            ->get();
    }

    public function forSite(?int $siteId): Collection
    {
        return EducationWalkthrough::active()
            ->when($siteId !== null, fn($q) => $q->forSite($siteId))
            ->with('steps')
            ->orderBy('sort_order')
            ->get();
    }
}
