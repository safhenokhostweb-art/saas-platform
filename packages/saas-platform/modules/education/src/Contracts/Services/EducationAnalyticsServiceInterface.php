<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

interface EducationAnalyticsServiceInterface
{
    /**
     * Analytics scoped to a single user.
     *
     * Returns:
     *   profile_scores  — skill, onboarding, adoption, engagement, overall
     *   completion_rate — fraction of progress records with status=completed
     *   recommendation_acceptance_rate — completed / (completed + dismissed) recommendations
     */
    public function userAnalytics(int $userId, ?int $siteId): array;

    /**
     * Analytics scoped to a site.
     *
     * Returns:
     *   module_adoption        — per-module unique-user counts
     *   gap_distribution       — Critical/High/Medium/Low module counts (approximated from usage)
     *   readiness_distribution — average / above-75 count / below-25 count
     */
    public function siteAnalytics(?int $siteId): array;

    /**
     * Platform-wide analytics (all sites, all users).
     *
     * Returns:
     *   global_adoption             — total unique users with any feature usage
     *   content_coverage_percent    — modules with ≥1 article / total modules
     *   recommendation_effectiveness — completed recommendations / total non-active
     */
    public function platformAnalytics(): array;

    /**
     * AI content generation analytics for a site (or platform-wide when null).
     *
     * Returns:
     *   drafts_created         — total successful generation log entries
     *   drafts_approved        — auto-generated articles now published
     *   drafts_rejected        — auto-generated articles archived
     *   generation_success_rate — success / total log entries (0.0–1.0)
     *   provider_usage         — ['provider_name' => count, ...]
     */
    public function generationAnalytics(?int $siteId): array;
}
