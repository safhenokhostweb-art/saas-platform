<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Models\EducationSyncLog;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;

class EducationSyncService
{
    // ── Role slugs used by learning-path auto-generation ──────────────────────
    private const SITE_ADMIN_ROLE  = 'admin';
    private const SUPPORT_ROLE     = 'support';
    private const RESELLER_ROLE    = 'reseller';

    public function __construct(
        private readonly DocumentationGeneratorService        $docGen,
        private readonly EducationWalkthroughGeneratorService $walkthroughGen,
        private readonly EducationLearningPathGeneratorService $pathGen,
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Full sync pipeline for a single site:
     *  1. Discover eligible features
     *  2. Provision missing documentation
     *  3. Archive documentation for disabled features
     *  4. Update learning paths per role
     *  5. Record sync log
     */
    public function syncForSite(int $siteId, bool $differential = true): array
    {
        $started = microtime(true);
        $site    = DB::table('sites')->where('id', $siteId)->whereNull('deleted_at')->first();

        if (! $site) {
            return $this->buildResult($siteId, 'failed', 0, 0, 0, 0, 0, [], 0, 'Site not found');
        }

        $eligible      = $this->discoverEligibleFeatures($siteId);
        $eligibleKeys  = $eligible->pluck('feature_key')->filter()->unique()->values();
        $eligibleModules = $eligible->pluck('module_slug')->unique()->values();

        $synced     = 0;
        $wSynced    = 0;
        $pSynced    = 0;
        $skipped    = 0;
        $archived   = 0;
        $details    = [];

        // F-16: Run sync work and write the sync log inside the same transaction so both
        // succeed or fail atomically. Catch exceptions to write a 'failed' log entry.
        $syncStatus  = 'success';
        $syncMessage = '';

        try {
            DB::transaction(function () use (
                $siteId, $eligible, $eligibleModules, $differential,
                &$synced, &$wSynced, &$pSynced, &$skipped, &$archived, &$details,
                &$started
            ) {
                // ── 1. Provision articles + walkthroughs ──────────────────────────
                foreach ($eligible as $feature) {
                    $result = $this->provisionFeature($feature, $siteId, $differential);
                    if ($result === 'synced') {
                        $synced++;
                        $details[] = "[SYNC] {$feature->module_slug}.{$feature->feature_slug}";
                    } elseif ($result === 'skipped') {
                        $skipped++;
                    } elseif ($result === 'regenerated') {
                        $synced++;
                        $details[] = "[REGEN] {$feature->module_slug}.{$feature->feature_slug}";
                    }

                    // Walkthrough
                    $wResult = $this->provisionWalkthrough($feature, $siteId, $differential);
                    if ($wResult !== 'skipped') {
                        $wSynced++;
                    }
                }

                // ── 2. Archive orphaned content ───────────────────────────────────
                $archived += $this->archiveOrphanedArticles($siteId, $eligibleModules->all());
                $this->archiveOrphanedWalkthroughs($siteId, $eligibleModules->all());

                // ── 3. Learning paths ─────────────────────────────────────────────
                $pSynced = $this->pathGen->updateForSite($siteId, $eligible);

                // ── 4. Write sync log inside transaction ──────────────────────────
                $duration = (int) ((microtime(true) - $started) * 1000);

                EducationSyncLog::create([
                    'site_id'               => $siteId,
                    'sync_type'             => $differential ? 'differential' : 'full',
                    'articles_synced'       => $synced,
                    'walkthroughs_synced'   => $wSynced,
                    'learning_paths_synced' => $pSynced,
                    'articles_archived'     => $archived,
                    'articles_skipped'      => $skipped,
                    'status'                => 'success',
                    'summary'               => ['details' => array_slice($details, 0, 50)],
                    'duration_ms'           => $duration,
                    'ran_at'                => now(),
                ]);
            });
        } catch (\Throwable $e) {
            $syncStatus  = 'failed';
            $syncMessage = $e->getMessage();

            // Best-effort failure log — outside the rolled-back transaction
            try {
                EducationSyncLog::create([
                    'site_id'     => $siteId,
                    'sync_type'   => $differential ? 'differential' : 'full',
                    'status'      => 'failed',
                    'summary'     => ['error' => $syncMessage],
                    'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                    'ran_at'      => now(),
                ]);
            } catch (\Throwable) {
                // ignore — log table might itself be unavailable
            }
        }

        $duration = (int) ((microtime(true) - $started) * 1000);

        return $this->buildResult($siteId, $syncStatus, $synced, $wSynced, $pSynced, $archived, $skipped, $details, $duration, $syncMessage);
    }

    /**
     * Sync all active sites.
     * Uses raw DB query to avoid SoftDeletes ambiguity (Site model does not use SoftDeletes trait).
     */
    public function syncAllSites(bool $differential = true): array
    {
        $sites   = DB::table('sites')->where('is_active', true)->whereNull('deleted_at')->pluck('id');
        $results = [];

        foreach ($sites as $siteId) {
            $results[$siteId] = $this->syncForSite((int) $siteId, $differential);
        }

        return $results;
    }

    /**
     * Verify sync state — report what is in sync and what is stale/missing.
     */
    public function verifySyncState(int $siteId): array
    {
        $eligible  = $this->discoverEligibleFeatures($siteId);
        $report    = [];
        $totalOk   = 0;
        $totalMiss = 0;
        $totalStale = 0;

        foreach ($eligible as $feature) {
            $article = EducationArticle::where('site_id', $siteId)
                ->where('module_slug', $feature->module_slug)
                ->where('status', '!=', EducationArticle::STATUS_ARCHIVED)
                ->first();

            if (! $article) {
                $totalMiss++;
                $report[] = ['feature' => $feature->feature_slug, 'status' => 'missing'];
                continue;
            }

            if ($article->needs_regeneration || $article->isHashStale()) {
                $totalStale++;
                $report[] = ['feature' => $feature->feature_slug, 'status' => 'stale', 'id' => $article->id];
                continue;
            }

            $totalOk++;
        }

        // Orphaned articles (for disabled features)
        $enabledModules = $eligible->pluck('module_slug')->unique()->values()->all();
        $orphaned = EducationArticle::where('site_id', $siteId)
            ->whereNotIn('module_slug', $enabledModules)
            ->where('status', '!=', EducationArticle::STATUS_ARCHIVED)
            ->count();

        return [
            'site_id'           => $siteId,
            'eligible_features' => $eligible->count(),
            'ok'                => $totalOk,
            'missing'           => $totalMiss,
            'stale'             => $totalStale,
            'orphaned'          => $orphaned,
            'report'            => $report,
            'verified_at'       => now()->toDateTimeString(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Eligibility — Rule 1 (5 conditions)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns the collection of EducationFeature records eligible for a site.
     * Applies all 5 conditions from Rule 1.
     *
     * Uses the GLOBAL feature catalog (site_id IS NULL) as the source of truth.
     * Per-site feature records created by FeatureDiscoveryService(site_id=X) are intentionally
     * excluded here to avoid double-processing the same feature.
     *
     * Uses raw DB queries against the confirmed schema (sites.title, no SoftDeletes model dependency).
     */
    public function discoverEligibleFeatures(int $siteId): Collection
    {
        $site = DB::table('sites')->where('id', $siteId)->whereNull('deleted_at')->first();
        if (! $site) {
            return collect();
        }

        // Modules installed and active on site (site_modules pivot → modules.slug)
        $installedModuleSlugs = DB::table('site_modules')
            ->join('modules', 'modules.id', '=', 'site_modules.module_id')
            ->where('site_modules.site_id', $siteId)
            ->where('site_modules.is_active', true)
            ->whereNull('site_modules.deleted_at')
            ->pluck('modules.slug')
            ->map(fn($s) => strtolower($s))
            ->all();

        // customer_menu_keys is not yet in the DB schema — fall back to allow-all (empty array).
        $menuKeys = DB::getSchemaBuilder()->hasColumn('sites', 'customer_menu_keys')
            ? (json_decode(DB::table('sites')->where('id', $siteId)->value('customer_menu_keys') ?? '[]', true) ?? [])
            : [];

        // Use ONLY the global feature catalog (site_id IS NULL) to avoid double-processing
        // site-specific feature duplicates created by FeatureDiscoveryService::discover($siteId).
        return EducationFeature::whereNull('site_id')
            ->get()
            ->filter(fn(EducationFeature $f) => $this->isFeatureEligible($f, $installedModuleSlugs, $menuKeys));
    }

    /**
     * Rule 1: A feature is eligible for a site when ALL 5 conditions pass:
     *  1. Related module is installed on customer site
     *  2. Related feature is enabled
     *  3. Route exists in customer-site route list (route_name not null)
     *  4. Feature is visible in customer-site menu
     *  5. (permission check deferred — platform-level articles are broadly accessible)
     */
    public function isFeatureEligible(EducationFeature $feature, array $installedModules, array $menuKeys): bool
    {
        // Condition 1 & 2: module installed and active
        $moduleLower = strtolower($feature->module_slug);
        if (! $this->moduleInstalledOnSite($moduleLower, $installedModules)) {
            return false;
        }

        // Condition 3: route must be non-null (placeholder features without routes skipped)
        if (empty($feature->route_name)) {
            return false;
        }

        // Condition 4: feature must appear in site menu or be a core platform feature
        if (! $this->isMenuVisibleForSite($feature, $menuKeys)) {
            return false;
        }

        return true;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private provision helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function provisionFeature(EducationFeature $feature, int $siteId, bool $differential): string
    {
        $existing = EducationArticle::where('site_id', $siteId)
            ->where('module_slug', $feature->module_slug)
            ->where('route_pattern', $feature->route_name)
            ->first();

        if (! $existing) {
            $article = $this->docGen->generateForFeature($feature, $siteId);
            if ($article) {
                $hash = $article->computeHash();
                $article->update([
                    'feature_key'           => $feature->feature_slug,
                    'documentation_hash'    => $hash,
                    'last_generated_at'     => now(),
                    'last_synced_at'        => now(),
                    'documentation_version' => 1,
                    'needs_regeneration'    => false,
                    'status'                => EducationArticle::STATUS_PUBLISHED,
                    'published_at'          => now(),
                ]);
            }
            return 'synced';
        }

        // Differential: skip if hash unchanged and not stale
        if ($differential && ! $existing->needs_regeneration && ! $existing->isHashStale()) {
            $existing->update(['last_synced_at' => now()]);
            return 'skipped';
        }

        // Regenerate
        $this->docGen->regenerateForFeature($feature, $siteId);
        $existing->refresh();
        $hash = $existing->computeHash();
        $existing->update([
            'documentation_hash'    => $hash,
            'last_generated_at'     => now(),
            'last_synced_at'        => now(),
            'documentation_version' => ($existing->documentation_version ?? 0) + 1,
            'needs_regeneration'    => false,
        ]);

        return 'regenerated';
    }

    private function provisionWalkthrough(EducationFeature $feature, int $siteId, bool $differential): string
    {
        $existing = EducationWalkthrough::where('site_id', $siteId)
            ->where('module_slug', $feature->module_slug)
            ->where('trigger_route', $feature->route_name)
            ->first();

        if (! $existing) {
            $this->walkthroughGen->generateForFeature($feature, $siteId);
            return 'synced';
        }

        if ($differential && ! $existing->needs_regeneration) {
            $existing->update(['last_synced_at' => now()]);
            return 'skipped';
        }

        $this->walkthroughGen->regenerateForFeature($feature, $existing);
        return 'regenerated';
    }

    private function archiveOrphanedArticles(int $siteId, array $enabledModules): int
    {
        return EducationArticle::where('site_id', $siteId)
            ->whereNotIn('module_slug', $enabledModules)
            ->where('status', '!=', EducationArticle::STATUS_ARCHIVED)
            ->update([
                'status'         => EducationArticle::STATUS_ARCHIVED,
                'last_synced_at' => now(),
            ]);
    }

    private function archiveOrphanedWalkthroughs(int $siteId, array $enabledModules): void
    {
        EducationWalkthrough::where('site_id', $siteId)
            ->whereNotIn('module_slug', $enabledModules)
            ->where('is_active', true)
            ->update(['is_active' => false, 'last_synced_at' => now()]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Eligibility helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function moduleInstalledOnSite(string $moduleSlug, array $installedModules): bool
    {
        // Only platform core features are unconditionally eligible.
        // 'tickets' and 'forms' require an explicit site_modules entry.
        $corePlatform = ['platform', 'education'];
        if (in_array($moduleSlug, $corePlatform, true)) {
            return true;
        }

        // 'tickets' module_slug may be installed as 'support' in site_modules
        if ($moduleSlug === 'tickets' && in_array('support', $installedModules, true)) {
            return true;
        }

        return in_array($moduleSlug, $installedModules, true);
    }

    private function isMenuVisibleForSite(EducationFeature $feature, array $menuKeys): bool
    {
        // If customer_menu_keys is not set, allow all
        if (empty($menuKeys)) {
            return true;
        }

        $featureSlug = $feature->feature_slug;
        $moduleSlug  = $feature->module_slug;

        // Check direct feature key
        if (in_array($featureSlug, $menuKeys, true)) {
            return true;
        }

        // Check module-level key
        if (in_array($moduleSlug, $menuKeys, true)) {
            return true;
        }

        // Core features always visible
        $coreKeys = ['dashboard', 'settings', 'users_panel', 'support', 'license', 'modules', 'activity_log'];
        if (in_array($featureSlug, $coreKeys, true) || in_array($moduleSlug, $coreKeys, true)) {
            return true;
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Utility
    // ──────────────────────────────────────────────────────────────────────────

    private function buildResult(
        int    $siteId,
        string $status,
        int    $synced,
        int    $wSynced,
        int    $pSynced,
        int    $archived,
        int    $skipped,
        array  $details,
        int    $durationMs,
        string $message = '',
    ): array {
        return [
            'site_id'               => $siteId,
            'status'                => $status,
            'articles_synced'       => $synced,
            'walkthroughs_synced'   => $wSynced,
            'learning_paths_synced' => $pSynced,
            'articles_archived'     => $archived,
            'articles_skipped'      => $skipped,
            'details'               => $details,
            'duration_ms'           => $durationMs,
            'message'               => $message,
        ];
    }
}
