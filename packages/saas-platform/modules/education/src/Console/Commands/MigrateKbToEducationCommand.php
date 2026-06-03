<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Education\Services\EducationMigrationService;

class MigrateKbToEducationCommand extends Command
{
    protected $signature   = 'education:migrate-kb {--dry-run : Show what would be migrated without writing}';
    protected $description = 'Migrate legacy kb_categories and kb_articles into edu_categories and edu_articles';

    public function handle(EducationMigrationService $migration): int
    {
        $tables = $migration->tablesExist();

        if (! $tables['kb_categories'] || ! $tables['kb_articles']) {
            $this->error('Legacy kb_* tables not found — nothing to migrate.');
            return self::FAILURE;
        }

        if (! $tables['edu_categories'] || ! $tables['edu_articles']) {
            $this->error('edu_* tables not found — run php artisan migrate first.');
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('[dry-run] Would migrate kb_categories → edu_categories and kb_articles → edu_articles.');
            return self::SUCCESS;
        }

        $this->info('Migrating kb_categories → edu_categories...');
        $cats = $migration->migrateKbCategories();
        $this->line("  Migrated {$cats} categories.");

        $this->info('Migrating kb_articles → edu_articles...');
        $arts = $migration->migrateKbArticles();
        $this->line("  Migrated {$arts} articles.");

        $this->info('Migration complete. Run php artisan route:clear and verify legacy compat routes.');
        return self::SUCCESS;
    }
}
