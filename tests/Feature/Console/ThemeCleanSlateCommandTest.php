<?php

namespace Tests\Feature\Console;

use App\Models\Customer;
use App\Models\Site;
use App\Models\Theme;
use App\Services\PublishedThemeAssignmentService;
use App\Services\ThemeBuilder\ThemeBuilderService;
use App\Services\ThemeBuilder\ThemeCleanSlate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * themes:clean-slate removes every platform theme record behind a verified,
 * restorable snapshot, and never touches sites, customers, users, platform
 * licenses or theme profiles.
 */
class ThemeCleanSlateCommandTest extends TestCase
{
    use RefreshDatabase;

    private const THEME_TABLES = [
        'themes', 'theme_releases', 'theme_release_dependencies', 'theme_licenses', 'theme_assignments',
        'theme_marketplace', 'theme_revisions', 'theme_quality_reports', 'menus', 'menu_items',
    ];

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'theme-clean-slate-'.uniqid();
        File::ensureDirectoryExists($this->storage.'/app');
        $this->app->useStoragePath($this->storage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    public function test_dry_run_lists_rows_and_changes_nothing(): void
    {
        $site = $this->assignedSite();
        $before = $this->dump();

        $this->artisan('themes:clean-slate', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame($before, $this->dump());
        $this->assertNotNull($site->fresh()->theme_id);
        $this->assertDirectoryDoesNotExist($this->storage.'/app/clean-slate');
    }

    public function test_purge_removes_theme_records_and_keeps_tenants(): void
    {
        $site = $this->assignedSite();
        $platformLicenses = DB::table('platform_licenses')->count();
        $this->assertGreaterThan(0, DB::table('theme_releases')->count());
        $this->assertGreaterThan(0, DB::table('theme_assignments')->count());

        $this->artisan('themes:clean-slate', ['--force' => true])->assertExitCode(0);

        foreach (self::THEME_TABLES as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} should be empty");
        }
        $this->assertNull($site->fresh()->theme_id);
        $this->assertTrue(Customer::whereKey($site->customer_id)->exists());
        $this->assertSame($platformLicenses, DB::table('platform_licenses')->count());

        $snapshots = File::directories($this->storage.'/app/clean-slate');
        $this->assertCount(1, $snapshots);
        ThemeCleanSlate::purger()->verify($snapshots[0]);
    }

    public function test_restore_puts_back_exactly_what_was_purged(): void
    {
        $this->assignedSite();
        $before = $this->dump();

        $this->artisan('themes:clean-slate', ['--force' => true])->assertExitCode(0);
        $snapshot = File::directories($this->storage.'/app/clean-slate')[0];

        $this->artisan('themes:clean-slate', ['--restore' => $snapshot, '--force' => true])->assertExitCode(0);

        $this->assertSame($before, $this->dump());
    }

    public function test_restore_refuses_to_merge_into_non_empty_tables(): void
    {
        $this->assignedSite();
        $purger = ThemeCleanSlate::purger();
        $dir = $this->storage.'/app/manual';
        $purger->snapshot($dir);

        $this->expectException(RuntimeException::class);
        $purger->restore($dir);
    }

    public function test_purge_refuses_when_rows_changed_after_the_snapshot(): void
    {
        $this->assignedSite();
        $purger = ThemeCleanSlate::purger();
        $dir = $this->storage.'/app/manual';
        $purger->snapshot($dir);
        $this->publishedTheme('late-arrival');
        $before = $this->dump();

        try {
            $purger->purge($dir);
            $this->fail('purge() must refuse a stale snapshot.');
        } catch (RuntimeException) {
        }

        $this->assertSame($before, $this->dump());
    }

    public function test_a_tampered_snapshot_fails_verification(): void
    {
        $this->assignedSite();
        $purger = ThemeCleanSlate::purger();
        $dir = $this->storage.'/app/manual';
        $manifest = $purger->snapshot($dir);
        $themesFile = collect($manifest['entries'])->firstWhere('name', 'themes')['file'];
        file_put_contents($dir.'/'.$themesFile, "{\"id\":1}\n", FILE_APPEND);

        $this->expectException(RuntimeException::class);
        $purger->verify($dir);
    }

    public function test_production_requires_the_database_name_and_non_interactive_runs_require_force(): void
    {
        $this->assignedSite();
        $before = $this->dump();

        $this->artisan('themes:clean-slate', ['--no-interaction' => true])->assertExitCode(1);

        $this->app['env'] = 'production';
        $this->artisan('themes:clean-slate', ['--force' => true])->assertExitCode(1);
        $this->artisan('themes:clean-slate', ['--force' => true, '--confirm-database' => 'some_other_db'])->assertExitCode(1);

        $this->assertSame($before, $this->dump());
        $this->assertDirectoryDoesNotExist($this->storage.'/app/clean-slate');
    }

    private function assignedSite(): Site
    {
        $customer = Customer::create(['name' => 'Clean slate', 'mobile' => '09'.random_int(100000000, 999999999)]);
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'Clean slate', 'domain' => 'clean-slate.test', 'is_active' => true]);
        $theme = $this->publishedTheme('clean-slate-theme');
        app(PublishedThemeAssignmentService::class)->assign($site, $theme);

        DB::table('theme_marketplace')->insert(['theme_id' => $theme->id, 'created_at' => now(), 'updated_at' => now()]);
        $menu = DB::table('menus')->insertGetId(['site_id' => $site->id, 'name' => 'Main', 'location' => 'header', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('menu_items')->insert(['menu_id' => $menu, 'title' => 'Home', 'url' => '/', 'created_at' => now(), 'updated_at' => now()]);

        return $site->fresh();
    }

    private function publishedTheme(string $slug): Theme
    {
        $theme = Theme::create([
            'name' => 'Theme '.$slug,
            'slug' => $slug,
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_MANUAL,
            'category' => Theme::CATEGORY_LANDING,
            'status' => Theme::STATUS_DRAFT,
            'is_marketplace' => true,
            'license_type' => Theme::LICENSE_FREE,
            'global_styles' => app(ThemeBuilderService::class)->defaultGlobalStyles(),
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => [
                'title' => 'Home', 'slug' => '', 'is_home' => true,
                'blocks' => [['id' => 'heading', 'type' => 'heading', 'props' => ['text' => $slug, 'level' => 1]]],
            ]],
        ]);
        app(ThemeBuilderService::class)->publish($theme, '1.0.0', 'clean slate test');

        return $theme->fresh();
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function dump(): array
    {
        $dump = [];
        foreach ([...self::THEME_TABLES, 'sites', 'customers', 'platform_licenses'] as $table) {
            $dump[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $dump;
    }
}
