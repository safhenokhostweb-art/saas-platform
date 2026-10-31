<?php

namespace Tests\Feature\DevOps;

use App\Models\SiteResourceLimit;
use App\Models\SiteResourceUsage;
use App\Models\TenantPruneAuditLog;
use App\Services\DevOps\DangerLevel;
use App\Services\DevOps\TenantResourceGovernor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\FakesTenantHost;
use Tests\TestCase;

class TenantResourceGovernorTest extends TestCase
{
    use RefreshDatabase;
    use FakesTenantHost;

    private const ROOT = '/var/www/cust_acme_12';
    private const GB   = 1073741824;

    private function governor(): TenantResourceGovernor
    {
        return $this->app->make(TenantResourceGovernor::class);
    }

    /** du reports KiB; 5 GB soft limit is the config default. */
    private function duResponse(float $gb, int $fsTotal = 100 * self::GB, int $fsUsed = 40 * self::GB): string
    {
        $kib = (int) round($gb * self::GB / 1024);
        $free = $fsTotal - $fsUsed;

        return "{$kib}\n/dev/vda1 {$fsTotal} {$fsUsed} {$free} 40% /\n";
    }

    public function test_evaluate_applies_zone_thresholds_to_soft_limit(): void
    {
        $g = $this->governor();

        $this->assertSame(DangerLevel::Normal, $g->evaluate((int) (3.9 * self::GB), 5 * self::GB)['level']);
        $this->assertSame(DangerLevel::Warning, $g->evaluate(4 * self::GB, 5 * self::GB)['level']);
        $this->assertSame(DangerLevel::Critical, $g->evaluate((int) (4.5 * self::GB), 5 * self::GB)['level']);
        $this->assertSame(90.0, $g->evaluate((int) (4.5 * self::GB), 5 * self::GB)['percent']);
        $this->assertSame(DangerLevel::Unknown, $g->evaluate(null, 5 * self::GB)['level']);
    }

    public function test_audit_persists_measured_usage_level_and_server_pool(): void
    {
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost(self::ROOT);
        $driver->addResponse('du -skx', 0, $this->duResponse(4.2));
        $this->bindTenantTarget($site, $driver, self::ROOT, serverId: 7);

        $result = $this->governor()->audit($site);

        $this->assertTrue($result['ok']);
        $this->assertSame(DangerLevel::Warning, $result['level']);

        $usage = SiteResourceUsage::where('site_id', $site->id)->first();
        $this->assertSame('warning', $usage->danger_level);
        $this->assertEqualsWithDelta(4.2 * self::GB, $usage->disk_usage_bytes, 1024);
        $this->assertNotNull($usage->disk_audited_at);
        $this->assertNull($usage->disk_audit_error);

        $pool = Cache::get(TenantResourceGovernor::POOL_CACHE_PREFIX . 7);
        $this->assertSame(100 * self::GB, $pool['total_bytes']);
        $this->assertSame(60 * self::GB, $pool['free_bytes']);

        $this->assertStringContainsString("du -skx -D '" . self::ROOT . "'", $driver->getCommandLog()[0]);
    }

    public function test_failed_audit_keeps_last_measurement_and_records_error(): void
    {
        $site = $this->makeDevOpsSite();
        SiteResourceUsage::create(['site_id' => $site->id, 'disk_usage_bytes' => 2 * self::GB, 'danger_level' => 'normal']);

        $driver = $this->fakeTenantHost(self::ROOT);
        $driver->addResponse('du -skx', 1, "du: cannot access\n");
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $result = $this->governor()->audit($site);

        $this->assertFalse($result['ok']);
        $usage = SiteResourceUsage::where('site_id', $site->id)->first();
        $this->assertSame(2 * self::GB, (int) $usage->disk_usage_bytes);
        $this->assertSame('normal', $usage->danger_level);
        $this->assertNotNull($usage->disk_audit_error);
    }

    public function test_warning_site_gets_routine_preview_when_auto_prune_is_off(): void
    {
        config(['resource_governance.prune.auto_enabled' => false]);
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost(self::ROOT, ['storage/logs' => ['old.log' => [10, 40 * 86400]]]);
        $driver->addResponse('du -skx', 0, $this->duResponse(4.2));
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $summary = $this->governor()->govern($site);

        $this->assertSame('previewed:routine', $summary['action']);
        $log = TenantPruneAuditLog::sole();
        $this->assertSame(TenantPruneAuditLog::MODE_DRY_RUN, $log->mode);
        $this->assertSame(TenantPruneAuditLog::TRIGGER_AUTO, $log->triggered_by);
        $this->assertSame([], $this->rmCommands($driver));
    }

    public function test_critical_site_is_pruned_aggressively_when_auto_prune_is_on(): void
    {
        config(['resource_governance.prune.auto_enabled' => true]);
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost(self::ROOT, ['storage/logs' => ['four-days.log' => [10, 4 * 86400]]]);
        $driver->addResponse('du -skx', 0, $this->duResponse(4.8));
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $summary = $this->governor()->govern($site);

        $this->assertSame('pruned:aggressive', $summary['action']);
        $log = TenantPruneAuditLog::sole();
        $this->assertSame(TenantPruneAuditLog::MODE_EXECUTE, $log->mode);
        $this->assertSame('aggressive', $log->profile);
        $this->assertCount(1, $this->rmCommands($driver));
        // measured again after pruning
        $this->assertCount(2, array_filter($driver->getCommandLog(), fn ($c) => str_contains($c, 'du -skx')));
    }

    public function test_normal_site_is_left_alone_and_dry_run_persists_nothing(): void
    {
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost(self::ROOT);
        $driver->addResponse('du -skx', 0, $this->duResponse(1.0));
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $this->assertSame('none', $this->governor()->govern($site, dryRun: true)['action']);
        $this->assertSame(0, SiteResourceUsage::count());

        $this->assertSame('none', $this->governor()->govern($site)['action']);
        $this->assertSame('normal', SiteResourceUsage::where('site_id', $site->id)->value('danger_level'));
        $this->assertSame(0, TenantPruneAuditLog::count());
    }

    public function test_matrix_uses_override_then_plan_default_then_config(): void
    {
        $a = $this->makeDevOpsSite('سایت الف');
        $b = $this->makeDevOpsSite('سایت ب');
        $c = $this->makeDevOpsSite('سایت ج');

        SiteResourceLimit::create(['site_id' => null, 'plan_name' => 'basic', 'disk_soft_limit_bytes' => 10 * self::GB]);
        SiteResourceLimit::create(['site_id' => $a->id, 'disk_soft_limit_bytes' => 2 * self::GB]);
        SiteResourceUsage::create(['site_id' => $a->id, 'disk_usage_bytes' => (int) (1.9 * self::GB), 'danger_level' => 'critical']);
        SiteResourceUsage::create(['site_id' => $b->id, 'disk_usage_bytes' => 1 * self::GB]);

        $rows = collect($this->governor()->tenantMatrix())->keyBy('site_id');

        $this->assertSame(2 * self::GB, $rows[$a->id]['soft_limit_bytes']);
        $this->assertSame('critical', $rows[$a->id]['danger_level']);
        $this->assertSame(10 * self::GB, $rows[$b->id]['soft_limit_bytes']);
        $this->assertSame('normal', $rows[$b->id]['danger_level']);
        $this->assertSame('unknown', $rows[$c->id]['danger_level']);

        $this->assertCount(1, $this->governor()->tenantMatrix('الف'));
    }

    public function test_config_default_applies_without_plan_row(): void
    {
        $site = $this->makeDevOpsSite();
        $row  = collect($this->governor()->tenantMatrix())->firstWhere('site_id', $site->id);

        $this->assertSame(5 * self::GB, $row['soft_limit_bytes']);
    }

    public function test_pool_summary_reports_overcommit_against_physical_disk(): void
    {
        $matrix = [
            ['server_id' => 7, 'soft_limit_bytes' => 5 * self::GB, 'disk_usage_bytes' => 1 * self::GB, 'danger_level' => 'normal'],
            ['server_id' => 7, 'soft_limit_bytes' => 5 * self::GB, 'disk_usage_bytes' => 4 * self::GB, 'danger_level' => 'warning'],
            ['server_id' => null, 'soft_limit_bytes' => 5 * self::GB, 'disk_usage_bytes' => null, 'danger_level' => 'unknown'],
        ];
        Cache::put(TenantResourceGovernor::POOL_CACHE_PREFIX . 7, ['total_bytes' => 8 * self::GB, 'used_bytes' => 6 * self::GB, 'free_bytes' => 2 * self::GB]);

        $pool = $this->governor()->poolSummary($matrix);

        $this->assertSame(1.25, $pool['overcommit_ratio']);     // 10 GB promised on an 8 GB disk
        $this->assertSame(15 * self::GB, $pool['allocated_bytes']);
        $this->assertSame(5 * self::GB, $pool['consumed_bytes']);
        $this->assertSame(2 * self::GB, $pool['physical_free_bytes']);
        $this->assertSame(1, $pool['servers_measured']);
        $this->assertSame(['normal' => 1, 'warning' => 1, 'critical' => 0, 'unknown' => 1], $pool['levels']);
    }

    public function test_pool_summary_without_measurements_has_no_ratio(): void
    {
        $pool = $this->governor()->poolSummary([]);

        $this->assertNull($pool['overcommit_ratio']);
        $this->assertNull($pool['physical_bytes']);
    }

    public function test_quota_update_seeds_new_override_from_plan_values(): void
    {
        $site = $this->makeDevOpsSite();
        SiteResourceLimit::create(['site_id' => null, 'plan_name' => 'basic', 'storage_limit_bytes' => 3 * self::GB, 'cpu_weight' => 40]);
        SiteResourceUsage::create(['site_id' => $site->id, 'disk_usage_bytes' => 4 * self::GB, 'danger_level' => 'normal']);

        $this->governor()->updateQuota($site, ['disk_soft_limit_bytes' => 4 * self::GB]);

        $override = SiteResourceLimit::where('site_id', $site->id)->sole();
        $this->assertSame(3 * self::GB, (int) $override->storage_limit_bytes, 'plan storage limit must carry over');
        $this->assertSame(40, (int) $override->cpu_weight);
        $this->assertNull($override->plan_name);
        $this->assertSame(4 * self::GB, (int) $override->disk_soft_limit_bytes);
        // 4 GB on a 4 GB quota → re-classified immediately
        $this->assertSame('critical', SiteResourceUsage::where('site_id', $site->id)->value('danger_level'));
    }

    public function test_govern_command_dry_run_and_input_validation(): void
    {
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost(self::ROOT);
        $driver->addResponse('du -skx', 0, $this->duResponse(4.5));
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $this->artisan('devops:govern-resources', ['--site' => $site->id, '--dry-run' => true])
            ->expectsOutputToContain('would_prune:aggressive')
            ->assertExitCode(0);
        $this->assertSame(0, SiteResourceUsage::count());

        $this->artisan('devops:govern-resources', ['--site' => 'abc'])->assertExitCode(2);
        $this->artisan('devops:govern-resources', ['--site' => 999999])->assertExitCode(1);
    }

    public function test_prune_command_dry_run_and_validation(): void
    {
        $site   = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost(self::ROOT, ['storage/logs' => ['old.log' => [2048, 40 * 86400]]]);
        $this->bindTenantTarget($site, $driver, self::ROOT);

        $this->artisan('devops:prune-tenant', ['tenant_id' => $site->id, '--dry-run' => true])
            ->expectsOutputToContain('[dry-run]')
            ->assertExitCode(0);
        $this->assertSame([], $this->rmCommands($driver));
        $this->assertSame(TenantPruneAuditLog::MODE_DRY_RUN, TenantPruneAuditLog::sole()->mode);

        $this->artisan('devops:prune-tenant', ['tenant_id' => '1;rm', '--dry-run' => true])->assertExitCode(2);
        $this->artisan('devops:prune-tenant', ['tenant_id' => $site->id, '--profile' => 'everything'])->assertExitCode(2);

        $this->artisan('devops:prune-tenant', ['tenant_id' => $site->id, '--force' => true])->assertExitCode(0);
        $this->assertCount(1, $this->rmCommands($driver));
    }

    public function test_command_alias_is_registered(): void
    {
        $this->assertArrayHasKey('devops:resources:govern', \Illuminate\Support\Facades\Artisan::all());
    }
}
