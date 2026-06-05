<?php

namespace SaaSPlatform\Modules\Education\Dto;

use Carbon\Carbon;

class UserLearningProfileDto
{
    public function __construct(
        public readonly int     $userId,
        public readonly ?int    $siteId,
        public readonly float   $skillScore,
        public readonly float   $onboardingScore,
        public readonly float   $adoptionScore,
        public readonly float   $engagementScore,
        public readonly array   $strongestModules,
        public readonly array   $weakestModules,
        public readonly ?Carbon $lastActivityAt,
    ) {}

    public function overallScore(): float
    {
        return round(
            ($this->skillScore * 0.25)
            + ($this->onboardingScore * 0.25)
            + ($this->adoptionScore * 0.30)
            + ($this->engagementScore * 0.20),
            2
        );
    }

    public function toArray(): array
    {
        return [
            'user_id'          => $this->userId,
            'site_id'          => $this->siteId,
            'skill_score'      => $this->skillScore,
            'onboarding_score' => $this->onboardingScore,
            'adoption_score'   => $this->adoptionScore,
            'engagement_score' => $this->engagementScore,
            'overall_score'    => $this->overallScore(),
            'strongest_modules'=> $this->strongestModules,
            'weakest_modules'  => $this->weakestModules,
            'last_activity_at' => $this->lastActivityAt?->toIso8601String(),
        ];
    }
}
