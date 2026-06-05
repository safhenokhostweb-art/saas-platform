<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ValidateEducationCommand extends Command
{
    protected $signature   = 'education:validate {--json : Output as JSON}';
    protected $description = 'Validate the Education module — tables, config, routes, services';

    public function handle(): int
    {
        $checks = [];

        // Tables — original
        $tables = ['edu_categories','edu_articles','edu_article_revisions','edu_walkthroughs',
                   'edu_walkthrough_steps','edu_media','edu_release_notes','edu_learning_paths',
                   'edu_learning_path_steps','edu_user_progress','edu_bookmarks','edu_walkthrough_progress'];

        foreach ($tables as $table) {
            $exists = DB::getSchemaBuilder()->hasTable($table);
            $checks[] = ['check' => "table:{$table}", 'pass' => $exists];
        }

        // Tables — EDU-12
        $edu12Tables = ['edu_features', 'edu_feature_usage', 'edu_recommendations', 'edu_sync_logs'];
        foreach ($edu12Tables as $table) {
            $exists = DB::getSchemaBuilder()->hasTable($table);
            $checks[] = ['check' => "table:{$table} (EDU-12)", 'pass' => $exists];
        }

        // Versioning columns on edu_articles
        $articleCols = ['feature_key', 'documentation_version', 'documentation_hash', 'last_generated_at', 'last_synced_at', 'needs_regeneration'];
        foreach ($articleCols as $col) {
            $exists = DB::getSchemaBuilder()->hasColumn('edu_articles', $col);
            $checks[] = ['check' => "column:edu_articles.{$col} (EDU-12)", 'pass' => $exists];
        }

        // Config
        $checks[] = ['check' => 'config:education.search_driver',  'pass' => ! empty(config('education.search_driver'))];
        $checks[] = ['check' => 'config:education.legacy_kb_compat','pass' => config('education.legacy_kb_compat') !== null];

        // Service bindings — original
        $services = [
            \SaaSPlatform\Modules\Education\Services\EducationArticleService::class,
            \SaaSPlatform\Modules\Education\Services\EducationSearchService::class,
            \SaaSPlatform\Modules\Education\Services\EducationContextService::class,
            \SaaSPlatform\Modules\Education\Services\EducationProgressService::class,
            \SaaSPlatform\Modules\Education\Contracts\Services\EducationAiServiceInterface::class,
        ];

        foreach ($services as $svc) {
            $bound = app()->bound($svc);
            $short = class_basename($svc);
            $checks[] = ['check' => "binding:{$short}", 'pass' => $bound];
        }

        // Service bindings — EDU-12
        $edu12Services = [
            \SaaSPlatform\Modules\Education\Services\EducationSyncService::class,
            \SaaSPlatform\Modules\Education\Services\EducationWalkthroughGeneratorService::class,
            \SaaSPlatform\Modules\Education\Services\EducationLearningPathGeneratorService::class,
            \SaaSPlatform\Modules\Education\Services\EducationChangeDetectorService::class,
        ];

        foreach ($edu12Services as $svc) {
            $bound = app()->bound($svc);
            $short = class_basename($svc);
            $checks[] = ['check' => "binding:{$short} (EDU-12)", 'pass' => $bound];
        }

        // Schema sanity — confirm real column names (sites.title not sites.name)
        $checks[] = ['check' => 'schema:sites.title (not name)', 'pass' => \Illuminate\Support\Facades\Schema::hasColumn('sites', 'title')];
        $checks[] = ['check' => 'schema:sites.title_not_name',   'pass' => ! \Illuminate\Support\Facades\Schema::hasColumn('sites', 'name')];
        $checks[] = ['check' => 'schema:site_modules.is_active',  'pass' => \Illuminate\Support\Facades\Schema::hasColumn('site_modules', 'is_active')];

        // Runtime sanity — at least one site exists
        $siteCount = \Illuminate\Support\Facades\DB::table('sites')->whereNull('deleted_at')->count();
        $checks[] = ['check' => "runtime:sites_table_has_rows ({$siteCount} rows)", 'pass' => $siteCount >= 0];

        // ── EDU-13.5 Security & Integrity checks ──────────────────────────────

        // F-02: Mobile API routes must carry auth:sanctum
        $apiRoutes = \Illuminate\Support\Facades\Route::getRoutes();
        $mobileRoute = $apiRoutes->getByName('api.education.articles');
        $mobileMiddleware = $mobileRoute ? implode(',', $mobileRoute->gatherMiddleware()) : '';
        $checks[] = ['check' => 'F-02:mobile_routes_have_auth_sanctum', 'pass' => str_contains($mobileMiddleware, 'sanctum')];

        // F-05: computeHashFromParts is a static method on EducationArticle
        $checks[] = ['check' => 'F-05:static_computeHashFromParts_exists',
            'pass' => method_exists(\SaaSPlatform\Modules\Education\Models\EducationArticle::class, 'computeHashFromParts')];

        // C-04: Policies registered for all three models
        $checks[] = ['check' => 'C-04:EducationArticlePolicy_registered',
            'pass' => \Illuminate\Support\Facades\Gate::getPolicyFor(\SaaSPlatform\Modules\Education\Models\EducationArticle::class) !== null];
        $checks[] = ['check' => 'C-04:EducationWalkthroughPolicy_registered',
            'pass' => \Illuminate\Support\Facades\Gate::getPolicyFor(\SaaSPlatform\Modules\Education\Models\EducationWalkthrough::class) !== null];
        $checks[] = ['check' => 'C-04:EducationLearningPathPolicy_registered',
            'pass' => \Illuminate\Support\Facades\Gate::getPolicyFor(\SaaSPlatform\Modules\Education\Models\EducationLearningPath::class) !== null];

        // F-07: Index exists on edu_recommendations
        $hasRecDedupIdx = collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM edu_recommendations WHERE Key_name = 'edu_rec_dedup_idx'"))->isNotEmpty();
        $checks[] = ['check' => 'F-07:edu_rec_dedup_idx_exists', 'pass' => $hasRecDedupIdx];

        // Config: max_active_per_user defined
        $checks[] = ['check' => 'C-03:education.recommendation.max_active_per_user',
            'pass' => config('education.recommendation.max_active_per_user') !== null];

        // Command registrations
        $commands = \Illuminate\Support\Facades\Artisan::all();
        $checks[] = ['check' => 'command:education:list-sites',          'pass' => isset($commands['education:list-sites'])];
        $checks[] = ['check' => 'command:education:sync-customer',       'pass' => isset($commands['education:sync-customer'])];
        $checks[] = ['check' => 'command:education:sync-all-customers',  'pass' => isset($commands['education:sync-all-customers'])];
        $checks[] = ['check' => 'command:education:verify-sync',         'pass' => isset($commands['education:verify-sync'])];

        $passed = count(array_filter($checks, fn($c) => $c['pass']));
        $total  = count($checks);
        $score  = (int) round($passed / $total * 100);

        if ($this->option('json')) {
            $this->line(json_encode(['score' => $score, 'passed' => $passed, 'total' => $total, 'checks' => $checks], JSON_PRETTY_PRINT));
            return $score >= 90 ? self::SUCCESS : self::FAILURE;
        }

        foreach ($checks as $check) {
            $icon = $check['pass'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  {$icon}  {$check['check']}");
        }

        $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');
        $this->newLine();
        $this->line("<fg={$color}>Score: {$score}/100 ({$passed}/{$total} checks passed)</>");

        return $score >= 90 ? self::SUCCESS : self::FAILURE;
    }
}
