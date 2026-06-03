<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureUsageRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationLearningPathRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Models\EducationLearningPathStep;
use SaaSPlatform\Modules\Education\Models\EducationUserProgress;

class EducationLearningPathService
{
    public function __construct(
        private readonly EducationLearningPathRepositoryInterface   $paths,
        private readonly EducationFeatureUsageRepositoryInterface   $usage,
    ) {}

    public function find(int $id): ?EducationLearningPath
    {
        return $this->paths->find($id);
    }

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->paths->paginate($filters, $perPage);
    }

    public function forRole(string $role, ?int $siteId): Collection
    {
        return $this->paths->forRole($role, $siteId);
    }

    public function create(array $data): EducationLearningPath
    {
        $steps = $data['steps'] ?? [];
        unset($data['steps']);

        $path = $this->paths->create($data);

        foreach ($steps as $i => $step) {
            $path->steps()->create(array_merge($step, ['step_number' => $i + 1]));
        }

        return $path->load('steps');
    }

    public function update(EducationLearningPath $path, array $data): EducationLearningPath
    {
        $steps = $data['steps'] ?? null;
        unset($data['steps']);

        $path = $this->paths->update($path, $data);

        if ($steps !== null) {
            $path->steps()->delete();
            foreach ($steps as $i => $step) {
                $path->steps()->create(array_merge($step, ['step_number' => $i + 1]));
            }
        }

        return $path->load('steps');
    }

    public function delete(EducationLearningPath $path): void
    {
        $this->paths->delete($path);
    }

    public function reorderSteps(EducationLearningPath $path, array $orderedIds): void
    {
        foreach ($orderedIds as $position => $stepId) {
            EducationLearningPathStep::where('id', $stepId)
                ->where('edu_learning_path_id', $path->id)
                ->update(['step_number' => $position + 1]);
        }
    }

    public function completionStats(EducationLearningPath $path, int $siteId): array
    {
        $total = $path->steps()->where('is_required', true)->count();

        return [
            'total_required_steps' => $path->steps()->where('is_required', true)->count(),
            'estimated_minutes'    => $path->estimated_minutes,
        ];
    }

    /** Return paths the user is eligible for (prerequisites met) filtered by role. */
    public function eligibleForUser(int $userId, ?int $siteId, string $role): Collection
    {
        $all = $this->paths->forRole($role, $siteId);

        return $all->filter(function (EducationLearningPath $path) use ($userId) {
            $prereqs = $path->prerequisite_path_ids ?? [];
            if (empty($prereqs)) {
                return true;
            }
            foreach ($prereqs as $prereqId) {
                $completed = EducationUserProgress::where('user_id', $userId)
                    ->where('trackable_type', EducationLearningPath::class)
                    ->where('trackable_id', $prereqId)
                    ->whereNotNull('completed_at')
                    ->exists();
                if (! $completed) {
                    return false;
                }
            }
            return true;
        })->values();
    }

    /** Return usage-based paths: triggered when a feature in their module_slug has low usage. */
    public function forUsageProfile(int $userId, ?int $siteId): Collection
    {
        $lowUsageModules = $this->usage->unusedFeatures($siteId)
            ->pluck('module_slug')
            ->unique()
            ->toArray();

        return EducationLearningPath::active()
            ->forSite($siteId)
            ->where('path_type', EducationLearningPath::TYPE_USAGE)
            ->whereIn('module_slug', $lowUsageModules)
            ->get();
    }
}
