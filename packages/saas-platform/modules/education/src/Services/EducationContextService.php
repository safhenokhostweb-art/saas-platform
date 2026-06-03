<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationArticleRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationWalkthroughRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationLearningPathRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationContextServiceInterface;

class EducationContextService implements EducationContextServiceInterface
{
    public function __construct(
        private readonly EducationArticleRepositoryInterface      $articles,
        private readonly EducationWalkthroughRepositoryInterface  $walkthroughs,
        private readonly EducationLearningPathRepositoryInterface  $learningPaths,
    ) {}

    public function recommendForCurrentRoute(?int $siteId, ?string $role): Collection
    {
        $routeName = $this->currentRouteName();

        if (! $routeName) {
            return collect();
        }

        return $this->articles->forRoute($routeName, $siteId, $role);
    }

    public function walkthroughsForCurrentRoute(?int $siteId, ?string $role): Collection
    {
        $routeName = $this->currentRouteName();

        if (! $routeName) {
            return collect();
        }

        return $this->walkthroughs->forRoute($routeName, $siteId)
            ->filter(function ($walkthrough) use ($role) {
                if (empty($walkthrough->target_roles)) {
                    return true;
                }
                return $role && in_array($role, $walkthrough->target_roles, true);
            })
            ->values();
    }

    public function learningPathsForRole(string $role, ?int $siteId): Collection
    {
        return $this->learningPaths->forRole($role, $siteId);
    }

    private function currentRouteName(): ?string
    {
        try {
            return Route::currentRouteName();
        } catch (\Throwable) {
            return null;
        }
    }
}
