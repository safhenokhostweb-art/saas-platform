<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\ModuleRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Commit 12-H — module:release-backfill scenarios.
 *
 * Uses the real "forms" package (packages/saas-platform/modules/forms),
 * whose module.json pins version 1.1.0, as the backfill candidate. The
 * command is scoped with --module=forms so the other real packages
 * (education, tickets) — which have no matching `modules` DB row in
 * this test — don't add noise to the assertions.
 */
class ModuleReleaseBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'forms';
    private const PACKAGE_VERSION = '1.1.0';

    /**
     * A migration seeds a base 'modules' row for slug 'forms' (see
     * 2026_02_23_120000_ensure_core_modules_blog_shop.php), so this must
     * update that row rather than assume the table is empty.
     */
    private function makeModule(string $version): Module
    {
        return Module::updateOrCreate(
            ['slug' => self::SLUG],
            [
                'title' => 'ماژول فرم',
                'description' => null,
                'is_active' => true,
                'version' => $version,
                'type' => Module::TYPE_CORE,
            ]
        );
    }

    public function test_backfill_creates_release_for_existing_module_with_no_prior_release(): void
    {
        $module = $this->makeModule('1.0.0');

        $exitCode = Artisan::call('module:release-backfill', ['--module' => self::SLUG]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('module_releases', [
            'module_id' => $module->id,
            'version' => self::PACKAGE_VERSION,
            'is_active' => true,
        ]);
        $this->assertSame(self::PACKAGE_VERSION, $module->fresh()->version);
    }

    public function test_backfill_skips_module_with_no_database_row(): void
    {
        // No Module row created — the package exists on disk, the DB row does not.
        $exitCode = Artisan::call('module:release-backfill', ['--module' => 'nonexistent-db-module-' . uniqid('', true)]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('module_releases', 0);
    }

    public function test_backfill_is_idempotent_across_reruns(): void
    {
        $module = $this->makeModule('1.0.0');

        Artisan::call('module:release-backfill', ['--module' => self::SLUG]);
        $this->assertSame(1, ModuleRelease::where('module_id', $module->id)->count());

        // Second run must not create a duplicate row for the same version.
        $exitCode = Artisan::call('module:release-backfill', ['--module' => self::SLUG]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, ModuleRelease::where('module_id', $module->id)->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $module = $this->makeModule('1.0.0');

        $exitCode = Artisan::call('module:release-backfill', [
            '--module' => self::SLUG,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('module_releases', 0);
        $this->assertSame('1.0.0', $module->fresh()->version);
    }
}
