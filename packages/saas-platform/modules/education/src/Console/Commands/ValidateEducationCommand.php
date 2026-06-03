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

        // Tables
        $tables = ['edu_categories','edu_articles','edu_article_revisions','edu_walkthroughs',
                   'edu_walkthrough_steps','edu_media','edu_release_notes','edu_learning_paths',
                   'edu_learning_path_steps','edu_user_progress','edu_bookmarks','edu_walkthrough_progress'];

        foreach ($tables as $table) {
            $exists = DB::getSchemaBuilder()->hasTable($table);
            $checks[] = ['check' => "table:{$table}", 'pass' => $exists];
        }

        // Config
        $checks[] = ['check' => 'config:education.search_driver',  'pass' => ! empty(config('education.search_driver'))];
        $checks[] = ['check' => 'config:education.legacy_kb_compat','pass' => config('education.legacy_kb_compat') !== null];

        // Service bindings
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
