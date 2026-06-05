<?php

namespace SaaSPlatform\Modules\Education\Services\Education;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use SaaSPlatform\Modules\Education\Contracts\Services\UserLearningProfileServiceInterface;
use SaaSPlatform\Modules\Education\Dto\UserLearningProfileDto;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;
use SaaSPlatform\Modules\Education\Models\EducationUserProgress;
use SaaSPlatform\Modules\Education\Models\EducationWalkthroughProgress;

class UserLearningProfileService implements UserLearningProfileServiceInterface
{
    private const CACHE_TTL_SECONDS = 900; // 15 minutes
    private const USAGE_DEPTH_CAP   = 20;  // usage_count normalised against this cap

    public function buildProfile(int $userId, ?int $siteId): UserLearningProfileDto
    {
        $cacheKey = $this->cacheKey($userId, $siteId);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($userId, $siteId) {
            return $this->compute($userId, $siteId);
        });
    }

    public function invalidate(int $userId, ?int $siteId): void
    {
        Cache::forget($this->cacheKey($userId, $siteId));
    }

    // -----------------------------------------------------------------------

    private function compute(int $userId, ?int $siteId): UserLearningProfileDto
    {
        $usageRows    = $this->loadUsage($userId, $siteId);
        $allFeatures  = $this->loadAllFeatures($siteId);
        $totalFeatures = $allFeatures->count();

        return new UserLearningProfileDto(
            userId:          $userId,
            siteId:          $siteId,
            skillScore:      $this->computeSkillScore($usageRows),
            onboardingScore: $this->computeOnboardingScore($userId, $siteId),
            adoptionScore:   $this->computeAdoptionScore($usageRows, $totalFeatures),
            engagementScore: $this->computeEngagementScore($userId, $siteId),
            strongestModules:$this->computeStrongestModules($usageRows, $allFeatures),
            weakestModules:  $this->computeWeakestModules($usageRows, $allFeatures),
            lastActivityAt:  $this->resolveLastActivity($userId, $siteId),
        );
    }

    private function loadUsage(int $userId, ?int $siteId): \Illuminate\Support\Collection
    {
        return EducationFeatureUsage::where('user_id', $userId)
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->get();
    }

    private function loadAllFeatures(?int $siteId): \Illuminate\Support\Collection
    {
        return EducationFeature::forSite($siteId)->get();
    }

    // skill_score: average normalised usage depth across features the user HAS used
    private function computeSkillScore(\Illuminate\Support\Collection $usageRows): float
    {
        if ($usageRows->isEmpty()) {
            return 0.0;
        }

        $avg = $usageRows->avg(function ($row) {
            return min($row->usage_count / self::USAGE_DEPTH_CAP, 1.0);
        });

        return round($avg * 100, 2);
    }

    // onboarding_score: completed walkthroughs / total available walkthroughs
    private function computeOnboardingScore(int $userId, ?int $siteId): float
    {
        // PA-02: both $total and $completed use identical site-scoping so the
        // denominator and numerator always represent the same walkthrough set.
        // siteId != null: global (site_id IS NULL) + site-specific walkthroughs
        // siteId == null: global walkthroughs only (platform admin scope)
        $scopeQuery = function ($q) use ($siteId) {
            if ($siteId !== null) {
                $q->where(fn($q2) => $q2->whereNull('site_id')->orWhere('site_id', $siteId));
            } else {
                $q->whereNull('site_id');
            }
        };

        $total = \SaaSPlatform\Modules\Education\Models\EducationWalkthrough::query()
            ->where('is_active', true)
            ->where($scopeQuery)
            ->count();

        if ($total === 0) {
            return 100.0;
        }

        $completed = EducationWalkthroughProgress::where('user_id', $userId)
            ->where('status', EducationWalkthroughProgress::STATUS_COMPLETED)
            ->where($scopeQuery)
            ->count();

        return round(min($completed / $total, 1.0) * 100, 2);
    }

    // adoption_score: distinct features used / total available features
    private function computeAdoptionScore(\Illuminate\Support\Collection $usageRows, int $totalFeatures): float
    {
        if ($totalFeatures === 0) {
            return 100.0;
        }

        $usedCount = $usageRows->count();

        return round(min($usedCount / $totalFeatures, 1.0) * 100, 2);
    }

    // engagement_score: article views (40%), rec completions (30%), path progress (30%)
    private function computeEngagementScore(int $userId, ?int $siteId): float
    {
        $articleViews = EducationUserProgress::where('user_id', $userId)
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->where('progressable_type', 'LIKE', '%Article%')
            ->count();

        $recCompleted = EducationRecommendation::forUser($userId)
            ->forSite($siteId)
            ->whereNotNull('completed_at')
            ->count();

        $pathCompleted = EducationUserProgress::where('user_id', $userId)
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->where('progressable_type', 'LIKE', '%LearningPath%')
            ->where('status', EducationUserProgress::STATUS_COMPLETED)
            ->count();

        // Normalise each component against a soft ceiling
        $articleNorm  = min($articleViews / 10, 1.0);
        $recNorm      = min($recCompleted / 5, 1.0);
        $pathNorm     = min($pathCompleted / 3, 1.0);

        $score = ($articleNorm * 40) + ($recNorm * 30) + ($pathNorm * 30);

        return round($score, 2);
    }

    // Returns top-3 module slugs by per-module adoption rate
    private function computeStrongestModules(
        \Illuminate\Support\Collection $usageRows,
        \Illuminate\Support\Collection $allFeatures
    ): array {
        return $this->moduleScores($usageRows, $allFeatures)
            ->sortByDesc('score')
            ->take(3)
            ->pluck('module')
            ->values()
            ->toArray();
    }

    // Returns bottom-3 module slugs (lowest adoption, at least 1 feature registered)
    private function computeWeakestModules(
        \Illuminate\Support\Collection $usageRows,
        \Illuminate\Support\Collection $allFeatures
    ): array {
        return $this->moduleScores($usageRows, $allFeatures)
            ->sortBy('score')
            ->take(3)
            ->pluck('module')
            ->values()
            ->toArray();
    }

    private function moduleScores(
        \Illuminate\Support\Collection $usageRows,
        \Illuminate\Support\Collection $allFeatures
    ): \Illuminate\Support\Collection {
        $usedByModule  = $usageRows->groupBy('module_slug')->map->count();
        $totalByModule = $allFeatures->groupBy('module_slug')->map->count();

        return $totalByModule->map(function (int $total, string $module) use ($usedByModule) {
            $used = $usedByModule->get($module, 0);
            return ['module' => $module, 'score' => $total > 0 ? ($used / $total) : 0];
        })->values();
    }

    private function resolveLastActivity(int $userId, ?int $siteId): ?Carbon
    {
        // F-05: explicit whereNull when siteId=null — mirrors loadUsage() scoping exactly
        $lastUsage = EducationFeatureUsage::where('user_id', $userId)
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->max('last_used_at');

        return $lastUsage ? Carbon::parse($lastUsage) : null;
    }

    private function cacheKey(int $userId, ?int $siteId): string
    {
        return "edu_profile.{$userId}." . ($siteId ?? 'null');
    }
}
