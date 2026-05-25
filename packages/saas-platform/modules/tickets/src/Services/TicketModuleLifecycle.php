<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Tickets\Contracts\ModuleLifecycleInterface;
use SaaSPlatform\CoreUtils\Support\ReleaseManifest;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;
use SaaSPlatform\CoreUtils\Services\PlatformCompatibilityService;
use SaaSPlatform\CoreUtils\Services\ModuleUpdateOrchestrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 7.1 — TASK 11: Canonical Lifecycle Integration
 *
 * Implements the full install/activate/update/rollback lifecycle for the
 * Tickets module. Integrates with CoreUtils orchestration services for
 * safe version transitions and rollback support.
 *
 * Follows the Forms ModuleLifecycleService as the canonical reference.
 */
class TicketModuleLifecycle implements ModuleLifecycleInterface
{
    public const VERSION = '1.1.0';
    public const SLUG    = 'tickets';

    protected const SCHEMA_TABLE = 'module_schema_versions';

    public function __construct(
        protected ReleaseManifest $releaseManifest,
        protected ModuleVersionRegistry $versionRegistry,
        protected PlatformCompatibilityService $compatibilityService,
        protected ModuleUpdateOrchestrator $orchestrator,
    ) {}

    public function install(): bool
    {
        try {
            DB::beginTransaction();

            $this->ensureSchemaTable();

            Artisan::call('vendor:publish', [
                '--provider' => 'SaaSPlatform\\Modules\\Tickets\\Providers\\TicketsServiceProvider',
                '--tag'      => 'tickets-config',
                '--force'    => true,
            ]);
            Artisan::call('vendor:publish', [
                '--provider' => 'SaaSPlatform\\Modules\\Tickets\\Providers\\TicketsServiceProvider',
                '--tag'      => 'tickets-migrations',
                '--force'    => true,
            ]);

            Artisan::call('migrate', ['--force' => true]);

            DB::table(self::SCHEMA_TABLE)->updateOrInsert(
                ['module_slug' => self::SLUG],
                ['version' => self::VERSION, 'status' => 'installed', 'updated_at' => now()]
            );

            Artisan::call('optimize:clear');

            DB::commit();

            Log::info('tickets.lifecycle.install.success', ['version' => self::VERSION]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('tickets.lifecycle.install.failed', [
                'version' => self::VERSION,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function uninstall(): bool
    {
        try {
            DB::beginTransaction();

            Schema::dropIfExists('ticket_replies');
            Schema::dropIfExists('tickets');

            $publishedViews = resource_path('views/vendor/tickets');
            if (File::isDirectory($publishedViews)) {
                File::deleteDirectory($publishedViews);
            }

            foreach (['tickets.php', 'module-manifest-tickets.php'] as $configFile) {
                $path = config_path($configFile);
                if (File::exists($path)) {
                    File::delete($path);
                }
            }

            DB::table(self::SCHEMA_TABLE)->where('module_slug', self::SLUG)->delete();

            DB::commit();

            Log::info('tickets.lifecycle.uninstall.success');

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('tickets.lifecycle.uninstall.failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function activate(int $siteId): bool
    {
        try {
            DB::table(self::SCHEMA_TABLE)
                ->where('module_slug', self::SLUG)
                ->update([
                    'status'     => 'active',
                    'notes'      => DB::raw("CONCAT(COALESCE(notes, ''), '\n[" . now() . "] Activated for site {$siteId}')"),
                    'updated_at' => now(),
                ]);

            Log::info('tickets.lifecycle.activate.success', ['site_id' => $siteId]);

            return true;
        } catch (\Throwable $e) {
            Log::error('tickets.lifecycle.activate.failed', [
                'site_id' => $siteId,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function deactivate(int $siteId): bool
    {
        try {
            DB::table(self::SCHEMA_TABLE)
                ->where('module_slug', self::SLUG)
                ->update([
                    'status'     => 'inactive',
                    'notes'      => DB::raw("CONCAT(COALESCE(notes, ''), '\n[" . now() . "] Deactivated for site {$siteId}')"),
                    'updated_at' => now(),
                ]);

            Log::info('tickets.lifecycle.deactivate.success', ['site_id' => $siteId]);

            return true;
        } catch (\Throwable $e) {
            Log::error('tickets.lifecycle.deactivate.failed', [
                'site_id' => $siteId,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function update(string $fromVersion, string $toVersion): bool
    {
        try {
            // Validate compatibility before update
            $check = $this->compatibilityService->check(self::SLUG, $toVersion);
            if (! $check['passed']) {
                Log::error('tickets.lifecycle.update.compatibility_failed', [
                    'errors' => $check['errors'],
                ]);
                return false;
            }

            $context = $this->orchestrator->prepareUpdate(self::SLUG);

            DB::beginTransaction();

            $this->runVersionMigrations($fromVersion, $toVersion);

            DB::table(self::SCHEMA_TABLE)->updateOrInsert(
                ['module_slug' => self::SLUG],
                [
                    'version'          => $toVersion,
                    'previous_version' => $fromVersion,
                    'status'           => 'updated',
                    'updated_at'       => now(),
                ]
            );

            Artisan::call('optimize:clear');

            DB::commit();

            $this->orchestrator->finalizeUpdate(self::SLUG, $context);

            Log::info('tickets.lifecycle.update.success', [
                'from' => $fromVersion,
                'to'   => $toVersion,
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('tickets.lifecycle.update.failed', [
                'from'  => $fromVersion,
                'to'    => $toVersion,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function rollback(string $currentVersion, string $targetVersion): bool
    {
        try {
            if (! $this->orchestrator->isRollbackSafe(self::SLUG)) {
                Log::error('tickets.lifecycle.rollback.unsafe', [
                    'current' => $currentVersion,
                    'target'  => $targetVersion,
                ]);
                return false;
            }

            DB::beginTransaction();

            $this->runRollbackMigrations($currentVersion, $targetVersion);

            DB::table(self::SCHEMA_TABLE)->updateOrInsert(
                ['module_slug' => self::SLUG],
                [
                    'version'          => $targetVersion,
                    'previous_version' => $currentVersion,
                    'status'           => 'rolled_back',
                    'updated_at'       => now(),
                ]
            );

            Artisan::call('optimize:clear');

            DB::commit();

            Log::info('tickets.lifecycle.rollback.success', [
                'from' => $currentVersion,
                'to'   => $targetVersion,
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('tickets.lifecycle.rollback.failed', [
                'from'  => $currentVersion,
                'to'    => $targetVersion,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getVersion(): string
    {
        return self::VERSION;
    }

    public function getSlug(): string
    {
        return self::SLUG;
    }

    protected function ensureSchemaTable(): void
    {
        if (Schema::hasTable(self::SCHEMA_TABLE)) {
            return;
        }

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

    protected function runVersionMigrations(string $fromVersion, string $toVersion): void
    {
        // Reserved for schema_version >= 2 migrations
        // Pattern: if (version_compare($fromVersion, 'x.y.z', '<') && ...) { Schema::table(...) }
    }

    protected function runRollbackMigrations(string $currentVersion, string $targetVersion): void
    {
        // Reserved: reverse of runVersionMigrations
    }

    /**
     * Proxy to ModuleUpdateOrchestrator::isRollbackSafe for external callers.
     */
    public function isRollbackSafe(): bool
    {
        return $this->orchestrator->isRollbackSafe(self::SLUG);
    }
}
