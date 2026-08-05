<?php

namespace SaaSPlatform\Modules\Education\Http\ViewComposers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationContextServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationRecommendationServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\WalkthroughTriggerServiceInterface;

class EducationGuideComposer
{
    public function __construct(
        private readonly EducationContextServiceInterface        $context,
        private readonly WalkthroughTriggerServiceInterface      $triggers,
        private readonly EducationRecommendationServiceInterface  $recs,
    ) {}

    // F-12: Guide composer TTL — 60 s keeps counts fresh without hammering the DB on every view.
    private const CACHE_TTL = 60;

    public function compose(View $view): void
    {
        if ($view->offsetExists('eduGuides')) {
            return;
        }

        $user      = auth()->user();
        $isAdmin   = $user && $user->is_admin;
        $role      = $isAdmin ? 'admin' : null;
        $userId    = $user?->id;
        $routeName = $this->currentRoute();

        // EDU-13.7 / F-15 regression fix: resolve siteId from the authenticated user's own
        // site_id column — the same fallback pattern used across all mobile controllers.
        // Admin panel requests never carry a tenant_site request attribute (that is set only
        // by AuthenticateTenantApi for license-key API routes).
        // Platform admins have user->site_id = null; site admins have an int value.
        $siteId = $user?->site_id;

        // F-12: Cache the expensive triple-query payload keyed on (userId, routeName).
        // Cache is bypassed for guests (userId = null) — they should not reach admin views anyway.
        $payload = $this->resolvePayload($userId, $siteId, $routeName, $role);

        $view->with('eduGuides',              $payload['guides']);
        $view->with('walkthroughTriggers',    $payload['walkthroughTriggers']);
        $view->with('eduRecommendationCount', $payload['eduRecommendationCount']);
    }

    private function resolvePayload(?int $userId, ?int $siteId, ?string $routeName, ?string $role): array
    {
        if (! $userId) {
            return $this->buildPayload($userId, $siteId, $routeName, $role);
        }

        $cacheKey = "edu_guide_composer.{$userId}.{$routeName}";

        // Cache::remember() talks to the cache backend (Redis in this app) outside any of the
        // try/catch guards in buildPayload(). If that backend is unreachable, this composer —
        // bound to every admin.* view — must degrade to an uncached lookup instead of throwing
        // and taking down the whole admin panel with a 500.
        try {
            return Cache::remember($cacheKey, self::CACHE_TTL, fn() => $this->buildPayload($userId, $siteId, $routeName, $role));
        } catch (\Throwable) {
            return $this->buildPayload($userId, $siteId, $routeName, $role);
        }
    }

    private function buildPayload(?int $userId, ?int $siteId, ?string $routeName, ?string $role): array
    {
        // --- contextual guides (articles matching current route) ---
        try {
            $guides = $this->context->recommendForCurrentRoute(null, $role);
        } catch (\Throwable) {
            $guides = collect();
        }

        // --- walkthrough auto-triggers ---
        // EDU-13.7: only trigger when siteId is non-null. Platform admins (siteId=null) are
        // excluded because edu_walkthrough_progress.site_id is NOT NULL (FK constraint), so
        // completion records cannot be stored or queried for them. Passing null would make the
        // completion-suppression query always return empty, causing every walkthrough to
        // re-trigger on every page load — the regression identified in EDU-13.6.
        $walkthroughTriggers = collect();
        if ($userId && $siteId !== null && $routeName && config('education.walkthroughs_enabled', true)) {
            try {
                $walkthroughTriggers = $this->triggers->triggersForRoute($routeName, $userId, $siteId);
            } catch (\Throwable) {
                // never break the page
            }
        }

        // --- active recommendations count (badge) ---
        $eduRecommendationCount = 0;
        if ($userId) {
            try {
                $eduRecommendationCount = $this->recs->activeForUser($userId, null, 99)->count();
            } catch (\Throwable) {
                // tables may not be migrated yet on first boot
            }
        }

        return compact('guides', 'walkthroughTriggers', 'eduRecommendationCount');
    }

    private function currentRoute(): ?string
    {
        try {
            return Route::currentRouteName();
        } catch (\Throwable) {
            return null;
        }
    }
}
