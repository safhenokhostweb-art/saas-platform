<?php

namespace SaaSPlatform\Modules\Forms\Services;

use SaaSPlatform\Modules\Forms\Contracts\ModuleLifecycleInterface;
use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormField;
use SaaSPlatform\Modules\Forms\Models\FormSubmission;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * TASK 1 — Module Lifecycle Hardening
 *
 * Canonical lifecycle service for the Forms module.
 * Implements ModuleLifecycleInterface with full install, uninstall,
 * activate, deactivate, update, and rollback support.
 *
 * This is the OFFICIAL REFERENCE implementation for all future modules.
 */
class ModuleLifecycleService implements ModuleLifecycleInterface
{
    /**
     * Current module version (semver).
     */
    public const VERSION = '1.1.0';

    /**
     * Module slug identifier.
     */
    public const SLUG = 'forms';

    /**
     * Schema version tracking table.
     */
    protected const SCHEMA_TABLE = 'module_schema_versions';

    /**
     * {@inheritdoc}
     */
    public function install(): bool
    {
        try {
            DB::beginTransaction();

            // 1. Ensure schema version tracking table exists
            if (!Schema::hasTable(self::SCHEMA_TABLE)) {
                Schema::create(self::SCHEMA_TABLE, function ($table) {
                    $table->id();
                    $table->string('module_slug', 100)->unique();
                    $table->string('version', 20);
                    $table->string('previous_version', 20)->nullable();
                    $table->string('status', 50)->default('installed');
                    $table->text('notes')->nullable();
                    $table->timestamps();
                });
            }

            // 2. Publish config, views, migrations
            Artisan::call('vendor:publish', [
                '--provider' => 'SaaSPlatform\\Modules\\Forms\\Providers\\FormsServiceProvider',
                '--tag' => 'forms-config',
                '--force' => true,
            ]);
            Artisan::call('vendor:publish', [
                '--provider' => 'SaaSPlatform\\Modules\\Forms\\Providers\\FormsServiceProvider',
                '--tag' => 'forms-views',
                '--force' => true,
            ]);
            Artisan::call('vendor:publish', [
                '--provider' => 'SaaSPlatform\\Modules\\Forms\\Providers\\FormsServiceProvider',
                '--tag' => 'forms-migrations',
                '--force' => true,
            ]);

            // 3. Run migrations
            Artisan::call('migrate', ['--force' => true]);

            // 4. Record schema version
            DB::table(self::SCHEMA_TABLE)->updateOrInsert(
                ['module_slug' => self::SLUG],
                [
                    'version' => self::VERSION,
                    'status' => 'installed',
                    'updated_at' => now(),
                ]
            );

            // 5. Clear cache
            Artisan::call('optimize:clear');

            DB::commit();

            Log::info('forms.lifecycle.install.success', [
                'version' => self::VERSION,
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('forms.lifecycle.install.failed', [
                'version' => self::VERSION,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(): bool
    {
        try {
            DB::beginTransaction();

            // 1. Drop module tables
            Schema::dropIfExists('form_submissions');
            Schema::dropIfExists('form_fields');
            Schema::dropIfExists('form_site');
            Schema::dropIfExists('forms');

            // 2. Remove published views
            $publishedViews = resource_path('views/vendor/forms');
            if (File::isDirectory($publishedViews)) {
                File::deleteDirectory($publishedViews);
            }

            // 3. Remove published config
            $configPath = config_path('forms.php');
            if (File::exists($configPath)) {
                File::delete($configPath);
            }

            $manifestPath = config_path('module-manifest-forms.php');
            if (File::exists($manifestPath)) {
                File::delete($manifestPath);
            }

            // 4. Remove schema version record
            DB::table(self::SCHEMA_TABLE)->where('module_slug', self::SLUG)->delete();

            DB::commit();

            Log::info('forms.lifecycle.uninstall.success');

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('forms.lifecycle.uninstall.failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function activate(int $siteId): bool
    {
        try {
            // Record activation in module_schema_versions log
            DB::table(self::SCHEMA_TABLE)
                ->where('module_slug', self::SLUG)
                ->update([
                    'status' => 'active',
                    'notes' => DB::raw("CONCAT(COALESCE(notes, ''), '\n[", now(), "] Activated for site {$siteId}')"),
                    'updated_at' => now(),
                ]);

            Log::info('forms.lifecycle.activate.success', [
                'site_id' => $siteId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('forms.lifecycle.activate.failed', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(int $siteId): bool
    {
        try {
            DB::table(self::SCHEMA_TABLE)
                ->where('module_slug', self::SLUG)
                ->update([
                    'status' => 'inactive',
                    'notes' => DB::raw("CONCAT(COALESCE(notes, ''), '\n[", now(), "] Deactivated for site {$siteId}')"),
                    'updated_at' => now(),
                ]);

            Log::info('forms.lifecycle.deactivate.success', [
                'site_id' => $siteId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('forms.lifecycle.deactivate.failed', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $fromVersion, string $toVersion): bool
    {
        try {
            DB::beginTransaction();

            // 1. Run any version-specific migrations
            $this->runVersionMigrations($fromVersion, $toVersion);

            // 2. Update schema version record
            DB::table(self::SCHEMA_TABLE)->updateOrInsert(
                ['module_slug' => self::SLUG],
                [
                    'version' => $toVersion,
                    'previous_version' => $fromVersion,
                    'status' => 'updated',
                    'notes' => DB::raw("COALESCE(notes, '') || '\n[" . now() . "] Updated from {$fromVersion} to {$toVersion}'"),
                    'updated_at' => now(),
                ]
            );

            // 3. Clear cache
            Artisan::call('optimize:clear');

            DB::commit();

            Log::info('forms.lifecycle.update.success', [
                'from' => $fromVersion,
                'to' => $toVersion,
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('forms.lifecycle.update.failed', [
                'from' => $fromVersion,
                'to' => $toVersion,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(string $currentVersion, string $targetVersion): bool
    {
        try {
            DB::beginTransaction();

            // 1. Run rollback migrations
            $this->runRollbackMigrations($currentVersion, $targetVersion);

            // 2. Update schema version record
            DB::table(self::SCHEMA_TABLE)->updateOrInsert(
                ['module_slug' => self::SLUG],
                [
                    'version' => $targetVersion,
                    'previous_version' => $currentVersion,
                    'status' => 'rolled_back',
                    'notes' => DB::raw("COALESCE(notes, '') || '\n[" . now() . "] Rolled back from {$currentVersion} to {$targetVersion}'"),
                    'updated_at' => now(),
                ]
            );

            // 3. Clear cache
            Artisan::call('optimize:clear');

            DB::commit();

            Log::info('forms.lifecycle.rollback.success', [
                'from' => $currentVersion,
                'to' => $targetVersion,
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('forms.lifecycle.rollback.failed', [
                'from' => $currentVersion,
                'to' => $targetVersion,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * {@inheritdoc}
     */
    public function getSlug(): string
    {
        return self::SLUG;
    }

    /**
     * Run version-specific migration logic.
     */
    protected function runVersionMigrations(string $fromVersion, string $toVersion): void
    {
        // Example: if upgrading from 1.0.0 to 1.1.0, add new columns
        if (version_compare($fromVersion, '1.0.0', '<') && version_compare($toVersion, '1.0.0', '>=')) {
            if (Schema::hasTable('forms') && !Schema::hasColumn('forms', 'meta_robots')) {
                Schema::table('forms', function ($table) {
                    $table->string('meta_robots', 80)->nullable()->after('canonical_url');
                });
            }
        }

        // Future migrations go here as version-specific blocks
    }

    /**
     * Run rollback migration logic.
     */
    protected function runRollbackMigrations(string $currentVersion, string $targetVersion): void
    {
        // Reverse of the above: if rolling back from >=1.0.0 to <1.0.0
        if (version_compare($currentVersion, '1.0.0', '>=') && version_compare($targetVersion, '1.0.0', '<')) {
            if (Schema::hasTable('forms') && Schema::hasColumn('forms', 'meta_robots')) {
                Schema::table('forms', function ($table) {
                    $table->dropColumn('meta_robots');
                });
            }
        }

        // Future rollback migrations go here
    }
}
