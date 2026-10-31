<?php

namespace Tests\Feature\DevOps;

use App\Models\TenantPruneAuditLog;
use App\Services\DevOps\Exceptions\PruneInProgressException;
use App\Services\DevOps\Exceptions\TenantUnreachableException;
use App\Services\DevOps\TenantStoragePruner;
use App\Services\DevOps\TenantTargetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\FakesTenantHost;
use Tests\TestCase;

class TenantStoragePrunerTest extends TestCase
{
    use RefreshDatabase;
    use FakesTenantHost;

    private const ROOT = '/var/www/cust_acme_12';
    private const DAY  = 86400;

    private function pruner(): TenantStoragePruner
    {
        return $this->app->make(TenantStoragePruner::class);
    }

    private function standardHost(): array
    {
        return [
            'storage/logs' => [
                'laravel-2026-09-01.log' => [5000, 40 * self::DAY],  // old → prune
                'laravel.log'            => [800, 3600],             // active → keep
                'notes.txt'              => [100, 90 * self::DAY],   // not a log → keep
            ],
            'storage/framework/views' => [
                'abc123.php' => [2000, 10 * self::DAY],              // stale → prune
                'def456.php' => [2000, 3600],                        // fresh → keep
            ],
        ];
    }

    public function test_dry_run_reports_without_deleting_anything(): void
    {
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost(self::ROOT, $this->standardHost());
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $log = $this->pruner()->preview($site, 'routine', TenantPruneAuditLog::TRIGGER_ADMIN, null);

        $this->assertSame(TenantPruneAuditLog::MODE_DRY_RUN, $log->mode);
        $this->assertSame(TenantPruneAuditLog::STATUS_COMPLETED, $log->status);
        $this->assertSame(2, $log->pruned_items_count);
        $this->assertSame(7000, $log->reclaimed_bytes);
        $this->assertSame(1, $log->details['categories']['logs']['count']);
        $this->assertSame(['storage/logs/laravel-2026-09-01.log'], $log->details['categories']['logs']['sample']);
        $this->assertSame([], $this->rmCommands($driver), 'dry run must never issue rm');
        $this->assertNotNull($log->finished_at);
    }

    public function test_execute_deletes_only_aged_matching_files(): void
    {
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost(self::ROOT, $this->standardHost());
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $log = $this->pruner()->execute($site, 'routine', TenantPruneAuditLog::TRIGGER_ADMIN, null);

        $this->assertSame(TenantPruneAuditLog::MODE_EXECUTE, $log->mode);
        $this->assertSame(TenantPruneAuditLog::STATUS_COMPLETED, $log->status);
        $this->assertSame(7000, $log->reclaimed_bytes);

        $rm = implode("\n", $this->rmCommands($driver));
        $this->assertStringContainsString("'" . self::ROOT . "/storage/logs/laravel-2026-09-01.log'", $rm);
        $this->assertStringContainsString("'" . self::ROOT . "/storage/framework/views/abc123.php'", $rm);
        $this->assertStringNotContainsString('laravel.log\'', $rm);
        $this->assertStringNotContainsString('notes.txt', $rm);
        $this->assertStringNotContainsString('def456.php', $rm);
        $this->assertStringStartsWith('rm -f -- ', $this->rmCommands($driver)[0]);
    }

    public function test_hostile_listing_entries_are_rejected_not_deleted(): void
    {
        $site  = $this->makeDevOpsSite();
        $old   = sprintf('%.10f', time() - 60 * self::DAY);
        $logs  = self::ROOT . '/storage/logs';
        $driver = $this->fakeTenantHost(self::ROOT, ['storage/logs' => []], extraRecords: ['storage/logs' => [
            "{$old}\t999\t{$logs}/../app/public/products/hero.log",   // traversal into media
            "{$old}\t999\t/etc/shadow.log",                            // outside the site
            "{$old}\t999\t/var/www/cust_other_13/storage/logs/x.log",  // another tenant
            "{$old}\t999\t" . self::ROOT . '/storage/app/public/a.log', // protected media
            'garbage-without-tabs',
        ]]);
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $log = $this->pruner()->execute($site, 'routine', TenantPruneAuditLog::TRIGGER_ADMIN, null);

        $this->assertSame(0, $log->pruned_items_count);
        $this->assertSame([], $this->rmCommands($driver));

        $reasons = array_column($log->details['rejected'], 'reason');
        $this->assertContains('not_normalized', $reasons);
        $this->assertContains('outside_target', $reasons);
        $this->assertContains('unparseable', $reasons);
        $this->assertCount(5, $log->details['rejected']);
    }

    public function test_symlinked_target_directory_is_skipped(): void
    {
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost(
            self::ROOT,
            ['storage/logs' => ['old.log' => [100, 40 * self::DAY]]],
            symlinkedDirs: ['storage/logs' => '/var/www/cust_acme_12/storage/app/public']
        );
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $log = $this->pruner()->execute($site, 'routine', TenantPruneAuditLog::TRIGGER_ADMIN, null);

        $this->assertSame(0, $log->pruned_items_count);
        $this->assertSame([['dir' => self::ROOT . '/storage/logs', 'reason' => 'symlinked_target']], $log->details['skipped']);
        $this->assertEmpty(array_filter($driver->getCommandLog(), fn ($c) => str_contains($c, 'find ')));
        $this->assertSame([], $this->rmCommands($driver));
    }

    public function test_database_dumps_always_keep_the_newest_copies(): void
    {
        $site  = $this->makeDevOpsSite();
        $dumps = [];
        foreach (range(1, 5) as $i) {
            $dumps["backup_2026_0{$i}.sql.gz"] = [1000, (60 - $i) * self::DAY]; // all older than 30 days
        }
        $driver = $this->fakeTenantHost(self::ROOT, ['storage/backups' => $dumps]);
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $log = $this->pruner()->execute($site, 'routine', TenantPruneAuditLog::TRIGGER_ADMIN, null);

        // routine profile keeps the newest 3 regardless of age
        $this->assertSame(2, $log->pruned_items_count);
        $rm = implode("\n", $this->rmCommands($driver));
        $this->assertStringContainsString('backup_2026_01.sql.gz', $rm);
        $this->assertStringContainsString('backup_2026_02.sql.gz', $rm);
        $this->assertStringNotContainsString('backup_2026_03.sql.gz', $rm);
        $this->assertStringNotContainsString('backup_2026_05.sql.gz', $rm);
    }

    public function test_aggressive_profile_uses_shorter_retention(): void
    {
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost(self::ROOT, ['storage/logs' => ['five-days.log' => [100, 5 * self::DAY]]]);
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $this->assertSame(0, $this->pruner()->preview($site, 'routine', TenantPruneAuditLog::TRIGGER_ADMIN)->pruned_items_count);
        $this->assertSame(1, $this->pruner()->preview($site, 'aggressive', TenantPruneAuditLog::TRIGGER_ADMIN)->pruned_items_count);
    }

    public function test_unsafe_web_root_fails_before_any_remote_listing(): void
    {
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost('/var/www', ['storage/logs' => ['x.log' => [1, 40 * self::DAY]]]);
        $this->bindTenantTarget($site, $driver, '/var/www');

        $log = $this->pruner()->execute($site, 'routine', TenantPruneAuditLog::TRIGGER_ADMIN, null);

        $this->assertSame(TenantPruneAuditLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('system directory', $log->error);
        $this->assertSame([], $driver->getCommandLog());
    }

    public function test_non_laravel_root_is_refused(): void
    {
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost(self::ROOT, ['storage/logs' => ['x.log' => [1, 40 * self::DAY]]], laravel: false);
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $log = $this->pruner()->execute($site, 'routine', TenantPruneAuditLog::TRIGGER_ADMIN, null);

        $this->assertSame(TenantPruneAuditLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('not a Laravel application', $log->error);
        $this->assertSame([], $this->rmCommands($driver));
    }

    public function test_failed_rm_batch_marks_run_partial(): void
    {
        config(['resource_governance.prune.delete_batch_size' => 1]);
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost(self::ROOT, ['storage/logs' => [
            'a.log' => [100, 40 * self::DAY],
            'b.log' => [200, 41 * self::DAY],
        ]]);
        $driver->addResponse("rm -f -- '" . self::ROOT . "/storage/logs/b.log'", 1, 'Permission denied');
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $log = $this->pruner()->execute($site, 'routine', TenantPruneAuditLog::TRIGGER_ADMIN, null);

        $this->assertSame(TenantPruneAuditLog::STATUS_PARTIAL, $log->status);
        $this->assertSame(1, $log->pruned_items_count);
        $this->assertSame(100, $log->reclaimed_bytes);
        $this->assertNotEmpty($log->details['errors']);
    }

    public function test_unreachable_tenant_is_audited_as_failed(): void
    {
        $site     = $this->makeDevOpsSite();
        $resolver = $this->createMock(TenantTargetResolver::class);
        $resolver->method('resolve')->willThrowException(new TenantUnreachableException('این سایت نصب کامل‌شده‌ای ندارد.'));
        $this->app->instance(TenantTargetResolver::class, $resolver);

        $log = $this->pruner()->preview($site, 'routine', TenantPruneAuditLog::TRIGGER_AUTO, null);

        $this->assertSame(TenantPruneAuditLog::STATUS_FAILED, $log->status);
        $this->assertSame(TenantPruneAuditLog::TRIGGER_AUTO, $log->triggered_by);
        $this->assertSame('این سایت نصب کامل‌شده‌ای ندارد.', $log->error);
    }

    public function test_concurrent_run_on_same_site_is_refused(): void
    {
        $site = $this->makeDevOpsSite();
        $this->bindTenantTarget($site, $this->fakeTenantHost(self::ROOT), self::ROOT);

        $lock = Cache::lock("devops:prune:site:{$site->id}", 60);
        $this->assertTrue($lock->get());

        try {
            $this->expectException(PruneInProgressException::class);
            $this->pruner()->execute($site, 'routine', TenantPruneAuditLog::TRIGGER_ADMIN, null);
        } finally {
            $lock->release();
            $this->assertSame(0, TenantPruneAuditLog::count());
        }
    }

    public function test_unknown_profile_and_trigger_are_rejected(): void
    {
        $site = $this->makeDevOpsSite();

        $this->expectException(\InvalidArgumentException::class);
        $this->pruner()->preview($site, 'nuke-everything', TenantPruneAuditLog::TRIGGER_ADMIN);
    }

    public function test_file_cap_limits_a_single_run(): void
    {
        config(['resource_governance.prune.max_files_per_run' => 2]);
        $site  = $this->makeDevOpsSite();
        $files = [];
        foreach (range(1, 5) as $i) {
            $files["old-{$i}.log"] = [10, 40 * self::DAY];
        }
        $driver = $this->fakeTenantHost(self::ROOT, ['storage/logs' => $files]);
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $log = $this->pruner()->preview($site, 'routine', TenantPruneAuditLog::TRIGGER_ADMIN);

        $this->assertSame(2, $log->pruned_items_count);
        $this->assertTrue($log->details['capped']);
    }
}
