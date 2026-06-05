<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Models\EducationLearningPathStep;

/**
 * Generates role-based learning paths.
 * Rule 7: paths auto-update when features change.
 */
class EducationLearningPathGeneratorService
{
    // ── Role definitions (Rule 7) ──────────────────────────────────────────────

    private const ROLE_PATHS = [
        'admin' => [
            'title'       => 'مسیر یادگیری مدیر سایت',
            'description' => 'یادگیری کامل برای مدیران سایت — از داشبورد تا پیشرفته‌ترین تنظیمات.',
            'module_order' => ['platform', 'forms', 'tickets', 'theme', 'education', 'store', 'resellers'],
            'estimated'   => 120,
        ],
        'support' => [
            'title'       => 'مسیر یادگیری اپراتور پشتیبانی',
            'description' => 'آموزش سریع برای اپراتورهای پشتیبانی — تیکت‌ها، مشتریان و ابزارهای ارتباطی.',
            'module_order' => ['tickets', 'platform', 'forms'],
            'estimated'   => 45,
        ],
        'reseller' => [
            'title'       => 'مسیر یادگیری نماینده فروش',
            'description' => 'آموزش برای نمایندگان — مشتریان، سایت‌ها و کمیسیون.',
            'module_order' => ['resellers', 'platform', 'store'],
            'estimated'   => 60,
        ],
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Update all role-based learning paths for a site given the current eligible features.
     * Returns the number of paths created or updated.
     */
    public function updateForSite(int $siteId, Collection $eligibleFeatures): int
    {
        $enabledModules = $eligibleFeatures->pluck('module_slug')->unique()->values()->all();
        $count = 0;

        foreach (self::ROLE_PATHS as $role => $config) {
            $this->upsertRolePath($role, $config, $siteId, $enabledModules);
            $count++;
        }

        // Archive learning paths for modules that are no longer eligible
        EducationLearningPath::where('site_id', $siteId)
            ->where('auto_generated', true)
            ->where('path_type', EducationLearningPath::TYPE_ROLE)
            ->whereNotIn('target_roles->0', array_keys(self::ROLE_PATHS))
            ->update([
                'is_active'       => false,
                'last_synced_at'  => now(),
                'needs_regeneration' => false,
            ]);

        return $count;
    }

    public function generateForRole(string $role, int $siteId, array $enabledModules = []): EducationLearningPath
    {
        $config = self::ROLE_PATHS[$role] ?? $this->buildGenericRoleConfig($role);
        return $this->upsertRolePath($role, $config, $siteId, $enabledModules);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private
    // ──────────────────────────────────────────────────────────────────────────

    private function upsertRolePath(string $role, array $config, int $siteId, array $enabledModules): EducationLearningPath
    {
        // Build steps: find articles for each module in the role's preferred order
        $steps = $this->buildStepsForRole($role, $config, $siteId, $enabledModules);

        $hash = hash('sha256', $role . '|' . $siteId . '|' . json_encode($steps));

        $existing = EducationLearningPath::where('site_id', $siteId)
            ->where('path_type', EducationLearningPath::TYPE_ROLE)
            ->whereJsonContains('target_roles', $role)
            ->where('auto_generated', true)
            ->first();

        $data = [
            'title'                 => $config['title'],
            'description'           => $config['description'],
            'site_id'               => $siteId,
            'path_type'             => EducationLearningPath::TYPE_ROLE,
            'target_roles'          => [$role],
            'auto_generated'        => true,
            'is_active'             => ! empty($steps),
            'estimated_minutes'     => $config['estimated'],
            'documentation_hash'    => $hash,
            'last_generated_at'     => now(),
            'last_synced_at'        => now(),
            'needs_regeneration'    => false,
        ];

        if ($existing) {
            if ($existing->documentation_hash === $hash) {
                $existing->update(['last_synced_at' => now()]);
                return $existing;
            }

            $existing->update(array_merge($data, [
                'documentation_version' => ($existing->documentation_version ?? 1) + 1,
            ]));
            $existing->steps()->delete();
            $this->createSteps($existing->id, $steps);
            return $existing->fresh();
        }

        $data['slug'] = 'path-' . $role . '-' . $siteId . '-' . Str::random(4);
        $data['documentation_version'] = 1;
        $path = EducationLearningPath::create($data);
        $this->createSteps($path->id, $steps);

        return $path;
    }

    private function buildStepsForRole(string $role, array $config, int $siteId, array $enabledModules): array
    {
        $order = $config['module_order'] ?? [];
        $steps = [];

        // Filter order to only enabled modules
        $filteredOrder = array_filter($order, fn($m) => in_array($m, $enabledModules, true) || in_array($m, ['platform', 'education'], true));

        foreach ($filteredOrder as $moduleSlug) {
            $articles = EducationArticle::where('site_id', $siteId)
                ->where('module_slug', $moduleSlug)
                ->where('status', EducationArticle::STATUS_PUBLISHED)
                ->orderBy('id')
                ->limit(3)
                ->get();

            foreach ($articles as $article) {
                $steps[] = [
                    'article_id'   => $article->id,
                    'title'        => $article->title,
                    'module'       => $moduleSlug,
                ];
            }
        }

        return $steps;
    }

    private function createSteps(int $pathId, array $steps): void
    {
        foreach ($steps as $i => $step) {
            EducationLearningPathStep::create([
                'edu_learning_path_id' => $pathId,
                'step_number'          => $i + 1,
                'title'                => $step['title'],
                'step_type'            => 'article',
                'reference_id'         => $step['article_id'],
                'reference_type'       => EducationArticle::class,
                'is_required'          => true,
                'meta'                 => ['module' => $step['module']],
            ]);
        }
    }

    private function buildGenericRoleConfig(string $role): array
    {
        return [
            'title'       => "مسیر یادگیری برای نقش {$role}",
            'description' => "مسیر یادگیری خودکار برای کاربران با نقش {$role}.",
            'module_order' => ['platform'],
            'estimated'   => 30,
        ];
    }
}
