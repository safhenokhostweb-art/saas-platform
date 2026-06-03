<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationWalkthroughRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use SaaSPlatform\Modules\Education\Models\EducationWalkthroughStep;

class EducationWalkthroughService
{
    public function __construct(
        private readonly EducationWalkthroughRepositoryInterface $walkthroughs,
    ) {}

    public function find(int $id): ?EducationWalkthrough
    {
        return $this->walkthroughs->find($id);
    }

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->walkthroughs->paginate($filters, $perPage);
    }

    public function create(array $data): EducationWalkthrough
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']) . '-' . Str::random(4);
        }
        $steps = $data['steps'] ?? [];
        unset($data['steps']);

        $walkthrough = $this->walkthroughs->create($data);

        foreach ($steps as $i => $step) {
            $walkthrough->steps()->create(array_merge($step, ['step_number' => $i + 1]));
        }

        return $walkthrough->load('steps');
    }

    public function update(EducationWalkthrough $walkthrough, array $data): EducationWalkthrough
    {
        $steps = $data['steps'] ?? null;
        unset($data['steps']);

        $walkthrough = $this->walkthroughs->update($walkthrough, $data);

        if ($steps !== null) {
            $walkthrough->steps()->delete();
            foreach ($steps as $i => $step) {
                $walkthrough->steps()->create(array_merge($step, ['step_number' => $i + 1]));
            }
        }

        return $walkthrough->load('steps');
    }

    public function delete(EducationWalkthrough $walkthrough): void
    {
        $this->walkthroughs->delete($walkthrough);
    }

    public function reorderSteps(EducationWalkthrough $walkthrough, array $orderedIds): void
    {
        foreach ($orderedIds as $position => $stepId) {
            EducationWalkthroughStep::where('id', $stepId)
                ->where('edu_walkthrough_id', $walkthrough->id)
                ->update(['step_number' => $position + 1]);
        }
    }
}
