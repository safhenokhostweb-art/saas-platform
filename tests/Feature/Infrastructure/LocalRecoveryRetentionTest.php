<?php

namespace Tests\Feature\Infrastructure;

use App\Console\Commands\BackupDatabaseCommand;
use App\Models\Infrastructure\LocalRecoveryLog;
use App\Services\Installer\LocalRecoveryRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LocalRecoveryRetentionTest extends TestCase
{
    use RefreshDatabase;

    private string $repo;

    private string $outside;

    protected function setUp(): void
    {
        parent::setUp();

        // validate() accepts only an absolute Linux-style path; on Windows a
        // drive-less "/..." path resolves on the current drive.
        $base = preg_replace('#^[A-Za-z]:#', '', str_replace('\\', '/', sys_get_temp_dir()))
            . '/lrr-retention-' . bin2hex(random_bytes(4));
        $this->repo = $base . DIRECTORY_SEPARATOR . 'repo';
        $this->outside = $base . DIRECTORY_SEPARATOR . 'outside';
        File::ensureDirectoryExists($this->repo);
        File::ensureDirectoryExists($this->outside);

        config([
            'installer.recovery_repository.local_path' => $this->repo,
            'installer.recovery_repository.policy' => 'both',
            'installer.recovery_repository.min_free_gb' => 0,
            'installer.recovery_repository.keep_per_scope' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->repo));
        parent::tearDown();
    }

    private function entry(string $type, ?int $siteId, string $label, string $status, int $minutesAgo, ?string $root = null): LocalRecoveryLog
    {
        $dir = ($root ?? $this->repo) . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $label;
        File::ensureDirectoryExists($dir);
        File::put($dir . DIRECTORY_SEPARATOR . 'database.sql.gz', str_repeat('x', 100));

        $log = LocalRecoveryLog::create([
            'type' => $type,
            'site_id' => $siteId,
            'baseline_label' => $label,
            'local_path' => $dir,
            'size_bytes' => 100,
            'status' => $status,
        ]);
        $log->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();

        return $log;
    }

    public function test_storing_a_customer_baseline_keeps_only_the_newest_two_for_that_site(): void
    {
        $repo = app(LocalRecoveryRepository::class);
        $dump = $this->outside . DIRECTORY_SEPARATOR . 'dump.sql.gz';
        File::put($dump, 'dump-bytes');
        $other = $this->entry('customer', 99, 'OTHER_SITE_OLD', 'verified', 500);

        $paths = [];
        foreach (range(1, 3) as $i) {
            $this->travel(1)->minutes();
            $paths[] = $repo->storeCustomerBaseline(7, 'uuid-7', $dump, hash_file('sha256', $dump))['local_path'];
        }

        $this->assertSame(2, LocalRecoveryLog::customer()->where('site_id', 7)->count());
        $this->assertDirectoryDoesNotExist($paths[0]);
        $this->assertDirectoryExists($paths[1]);
        $this->assertDirectoryExists($paths[2]);
        // Another site is not touched by site 7's retention.
        $this->assertDirectoryExists($other->local_path);
        $this->assertNotNull($other->fresh());
    }

    public function test_dry_run_command_lists_candidates_and_deletes_nothing(): void
    {
        $old = $this->entry('platform', null, 'INSTALLATION_BASELINE_1', 'verified', 30);
        $this->entry('platform', null, 'INSTALLATION_BASELINE_2', 'verified', 20);
        $this->entry('platform', null, 'INSTALLATION_BASELINE_3', 'verified', 10);

        $this->artisan('recovery:prune-local')
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('1 item(s)')
            ->assertSuccessful();

        $this->assertDirectoryExists($old->local_path);
        $this->assertNotNull($old->fresh());
    }

    public function test_execute_prunes_older_platform_copies_but_never_the_initial_baseline(): void
    {
        $initial = $this->entry('platform', null, 'INITIAL_INSTALLATION_BASELINE', 'verified', 100);
        $old = $this->entry('platform', null, 'INSTALLATION_BASELINE_1', 'verified', 30);
        $keepA = $this->entry('platform', null, 'INSTALLATION_BASELINE_2', 'verified', 20);
        $keepB = $this->entry('platform', null, 'INSTALLATION_BASELINE_3', 'verified', 10);

        $this->artisan('recovery:prune-local', ['--execute' => true])->assertSuccessful();

        $this->assertNull($old->fresh());
        $this->assertDirectoryDoesNotExist($old->local_path);
        foreach ([$initial, $keepA, $keepB] as $kept) {
            $this->assertNotNull($kept->fresh());
            $this->assertDirectoryExists($kept->local_path);
        }
    }

    public function test_newer_failed_copies_never_cost_the_last_verified_ones(): void
    {
        $verifiedOld = $this->entry('customer', 5, 'C1', 'verified', 40);
        $verifiedNew = $this->entry('customer', 5, 'C2', 'verified', 30);
        $this->entry('customer', 5, 'C3', 'failed', 20);
        $this->entry('customer', 5, 'C4', 'failed', 10);

        $result = app(LocalRecoveryRepository::class)->prune(null, false);

        $this->assertSame([], $result['items']);
        $this->assertNotNull($verifiedOld->fresh());
        $this->assertNotNull($verifiedNew->fresh());
    }

    public function test_a_logged_path_outside_the_repository_is_never_deleted(): void
    {
        $outside = $this->entry('customer', 3, 'OUTSIDE', 'verified', 40, $this->outside);
        $this->entry('customer', 3, 'C2', 'verified', 20);
        $this->entry('customer', 3, 'C3', 'verified', 10);

        $result = app(LocalRecoveryRepository::class)->prune(null, false);

        $this->assertSame('skipped_outside_repository', $result['items'][0]['action']);
        $this->assertDirectoryExists($outside->local_path);
        $this->assertNotNull($outside->fresh());
    }

    public function test_db_backup_keeps_two_local_and_seven_offsite_copies_by_default(): void
    {
        $definition = $this->app->make(BackupDatabaseCommand::class)->getDefinition();
        $this->assertSame('2', $definition->getOption('retention')->getDefault());
        $this->assertSame('7', $definition->getOption('remote-retention')->getDefault());

        $scheduled = collect($this->app->make(Schedule::class)->events())
            ->map(fn ($event) => (string) $event->command)
            ->first(fn (string $command) => str_contains($command, 'db:backup ') || str_ends_with($command, 'db:backup'));
        $this->assertNotNull($scheduled);
        $this->assertStringContainsString('--retention=2', $scheduled);
        $this->assertStringContainsString('--remote-retention=7', $scheduled);
    }
}
