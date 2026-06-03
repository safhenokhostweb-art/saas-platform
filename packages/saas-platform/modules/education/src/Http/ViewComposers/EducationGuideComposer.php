<?php

namespace SaaSPlatform\Modules\Education\Http\ViewComposers;

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

        // --- contextual guides (articles matching current route) ---
        try {
            $guides = $this->context->recommendForCurrentRoute(null, $role);
        } catch (\Throwable) {
            $guides = collect();
        }

        // --- walkthrough auto-triggers ---
        $walkthroughTriggers = collect();
        if ($userId && $routeName && config('education.walkthroughs_enabled', true)) {
            try {
                $walkthroughTriggers = $this->triggers->triggersForRoute($routeName, $userId, null);
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

        $view->with('eduGuides', $guides);
        $view->with('walkthroughTriggers', $walkthroughTriggers);
        $view->with('eduRecommendationCount', $eduRecommendationCount);
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
