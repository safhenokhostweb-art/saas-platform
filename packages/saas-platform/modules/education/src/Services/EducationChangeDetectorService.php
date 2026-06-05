<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;

/**
 * Detects changes in feature metadata and marks affected content for regeneration.
 * Rule 9: monitors routes, manifests, menus, and article hashes.
 */
class EducationChangeDetectorService
{
    private const CACHE_PREFIX = 'edu_change_detector_';
    private const CACHE_TTL    = 3600; // 1 hour

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scan all auto-generated content for stale hashes.
     * Marks needs_regeneration = true on anything that has drifted.
     * Returns count of items flagged.
     */
    public function detectChanges(?int $siteId = null): int
    {
        $flagged = 0;

        $flagged += $this->detectStaleArticles($siteId);
        $flagged += $this->detectStaleWalkthroughs($siteId);
        $flagged += $this->detectStaleFeatureChanges($siteId);

        return $flagged;
    }

    /**
     * Recompute and store a hash for a feature.
     * Returns true if the hash changed (indicating regeneration needed).
     */
    public function detectFeatureChange(EducationFeature $feature): bool
    {
        $currentHash = $this->computeFeatureHash($feature);
        $cacheKey    = self::CACHE_PREFIX . 'feature_' . $feature->id;
        $storedHash  = Cache::get($cacheKey);

        if ($storedHash === null) {
            Cache::put($cacheKey, $currentHash, self::CACHE_TTL);
            return false;
        }

        if ($storedHash !== $currentHash) {
            Cache::put($cacheKey, $currentHash, self::CACHE_TTL);
            $this->flagArticlesForFeature($feature);
            return true;
        }

        return false;
    }

    /**
     * Compute a deterministic hash for an article's content.
     */
    public function computeArticleHash(EducationArticle $article): string
    {
        return hash('sha256', $article->title . '|' . $article->body . '|' . $article->excerpt);
    }

    /**
     * Compute a hash for a feature's metadata (route, permissions, menu).
     */
    public function computeFeatureHash(EducationFeature $feature): string
    {
        return hash('sha256', implode('|', [
            $feature->module_slug,
            $feature->feature_slug,
            $feature->feature_name,
            $feature->route_name ?? '',
            json_encode($feature->permissions_json ?? []),
            json_encode($feature->metadata_json ?? []),
        ]));
    }

    /**
     * Force-mark all auto-generated articles for a module as needing regeneration.
     */
    public function markModuleForRegeneration(string $moduleSlug, ?int $siteId = null): int
    {
        $query = EducationArticle::where('module_slug', $moduleSlug)
            ->where('auto_generated', true);

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->update(['needs_regeneration' => true]);
    }

    /**
     * Generate a sync manifest for a site: article slugs → hashes.
     * Used by customer-site for differential sync.
     */
    public function buildSyncManifest(int $siteId): array
    {
        $manifest = [];

        EducationArticle::where('site_id', $siteId)
            ->where('status', EducationArticle::STATUS_PUBLISHED)
            ->select(['id', 'slug', 'module_slug', 'feature_key', 'documentation_hash', 'documentation_version', 'updated_at'])
            ->get()
            ->each(function (EducationArticle $article) use (&$manifest) {
                $manifest[$article->slug] = [
                    'id'                    => $article->id,
                    'module_slug'           => $article->module_slug,
                    'feature_key'           => $article->feature_key,
                    'documentation_hash'    => $article->documentation_hash ?? $this->computeArticleHash($article),
                    'documentation_version' => $article->documentation_version ?? 1,
                    'updated_at'            => $article->updated_at?->toISOString(),
                ];
            });

        return $manifest;
    }

    /**
     * Compare a client-provided manifest against the server's current manifest.
     * Returns arrays of: new, updated, and removed slugs.
     */
    public function diffManifests(int $siteId, array $clientManifest): array
    {
        $serverManifest = $this->buildSyncManifest($siteId);

        $serverSlugs = array_keys($serverManifest);
        $clientSlugs = array_keys($clientManifest);

        $newSlugs     = array_diff($serverSlugs, $clientSlugs);
        $removedSlugs = array_diff($clientSlugs, $serverSlugs);
        $updatedSlugs = [];

        foreach (array_intersect($serverSlugs, $clientSlugs) as $slug) {
            $serverHash = $serverManifest[$slug]['documentation_hash'];
            $clientHash = $clientManifest[$slug]['documentation_hash'] ?? null;
            if ($serverHash !== $clientHash) {
                $updatedSlugs[] = $slug;
            }
        }

        return [
            'new'     => array_values($newSlugs),
            'updated' => $updatedSlugs,
            'removed' => array_values($removedSlugs),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private detection logic
    // ──────────────────────────────────────────────────────────────────────────

    private function detectStaleArticles(?int $siteId): int
    {
        $query = EducationArticle::where('auto_generated', true)
            ->where('needs_regeneration', false);

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        $flagged = 0;
        $query->chunkById(100, function ($articles) use (&$flagged) {
            foreach ($articles as $article) {
                $current = $this->computeArticleHash($article);
                if ($article->documentation_hash && $article->documentation_hash !== $current) {
                    $article->update(['needs_regeneration' => true]);
                    $flagged++;
                } elseif (! $article->documentation_hash) {
                    // No hash recorded — store it
                    $article->update(['documentation_hash' => $current]);
                }
            }
        });

        return $flagged;
    }

    private function detectStaleWalkthroughs(?int $siteId): int
    {
        $query = EducationWalkthrough::where('auto_generated', true)
            ->where('needs_regeneration', false);

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        $flagged = 0;
        $query->chunkById(100, function ($walkthroughs) use (&$flagged) {
            foreach ($walkthroughs as $w) {
                // Walkthroughs are flagged if their feature metadata changed
                $feature = EducationFeature::where('module_slug', $w->module_slug)
                    ->where('feature_slug', $w->feature_key ?? $w->module_slug)
                    ->first();

                if ($feature && $this->detectFeatureChange($feature)) {
                    $w->update(['needs_regeneration' => true]);
                    $flagged++;
                }
            }
        });

        return $flagged;
    }

    private function detectStaleFeatureChanges(?int $siteId): int
    {
        $query = EducationFeature::query();
        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        $flagged = 0;
        $query->chunkById(100, function ($features) use (&$flagged) {
            foreach ($features as $feature) {
                if ($this->detectFeatureChange($feature)) {
                    $count = EducationArticle::where('module_slug', $feature->module_slug)
                        ->where('auto_generated', true)
                        ->update(['needs_regeneration' => true]);
                    $flagged += $count;
                }
            }
        });

        return $flagged;
    }

    private function flagArticlesForFeature(EducationFeature $feature): void
    {
        EducationArticle::where('module_slug', $feature->module_slug)
            ->where('auto_generated', true)
            ->update(['needs_regeneration' => true]);
    }
}
