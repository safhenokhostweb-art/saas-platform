<?php

namespace App\Services\DevOps;

use App\Models\CustomerInstallation;
use App\Models\Site;
use App\Models\SiteResourceLimit;
use App\Models\SiteResourceUsage;
use App\Models\TenantPruneAuditLog;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\DevOps\Exceptions\PruneInProgressException;
use App\Services\Notification\NotificationCenterService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Measures each site's real web-root disk usage against its thin-provisioned
 * soft quota, classifies it into a danger zone, and decides what the
 * scheduler does about it:
 *
 *   normal   → nothing
 *   warning  → "routine" prune profile (logs, cache, views, sessions…)
 *   critical → "aggressive" profile + one admin notification on escalation
 *
 * Pruning executes only when resource_governance.prune.auto_enabled is on;
 * otherwise the scheduler records a dry-run preview for an admin to act on.
 *
 * Quotas come from site_resource_limits (per-site override → plan default →
 * config), the same precedence as SiteResourceLimit::defaultForSite().
 */
class TenantResourceGovernor
{
    public const POOL_CACHE_PREFIX = 'devops:pool:server:';

    public function __construct(
        private readonly TenantTargetResolver $targets,
        private readonly TenantStoragePruner  $pruner,
        private readonly PrunePathGuard       $guard,
    ) {}

    // ─── Evaluation ──────────────────────────────────────────────────────────

    /**
     * @return array{percent: float|null, level: DangerLevel}
     */
    public function evaluate(?int $usedBytes, int $softLimitBytes): array
    {
        $percent = ($usedBytes === null || $softLimitBytes <= 0)
            ? null
            : round($usedBytes / $softLimitBytes * 100, 1);

        return ['percent' => $percent, 'level' => DangerLevel::fromPercent($percent)];
    }

    // ─── Measurement ─────────────────────────────────────────────────────────

    /**
     * Measure one site and persist the result. With $persist = false nothing
     * is written (used by the command's --dry-run).
     */
    public function audit(Site $site, bool $persist = true): array
    {
        $usage    = SiteResourceUsage::firstOrNew(['site_id' => $site->id]);
        $limit    = SiteResourceLimit::defaultForSite($site);
        $previous = DangerLevel::tryFrom((string) $usage->danger_level) ?? DangerLevel::Unknown;
        $soft     = $limit->effectiveDiskSoftLimit();

        try {
            $target = $this->targets->resolve($site);
            $root   = $this->guard->assertSafeWebRoot($target->webRoot);
            $target->driver->connect();

            try {
                $measured = $this->measure($target->driver, $root);
            } finally {
                try {
                    $target->driver->disconnect();
                } catch (\Throwable) {
                }
            }
        } catch (\Throwable $e) {
            if ($persist) {
                $usage->fill(['disk_audit_error' => mb_substr($e->getMessage(), 0, 500)]);
                $usage->save();
            }

            return [
                'site_id'        => $site->id,
                'ok'             => false,
                'error'          => $e->getMessage(),
                'level'          => $previous,
                'previous_level' => $previous,
                'percent'        => null,
            ];
        }

        $eval = $this->evaluate($measured['used_bytes'], $soft);

        if ($persist) {
            $usage->fill([
                'disk_usage_bytes' => $measured['used_bytes'],
                'danger_level'     => $eval['level']->value,
                'disk_audited_at'  => now(),
                'disk_audit_error' => null,
            ]);
            $usage->save();

            if ($target->serverId && $measured['fs_total_bytes']) {
                Cache::put(self::POOL_CACHE_PREFIX . $target->serverId, [
                    'total_bytes' => $measured['fs_total_bytes'],
                    'used_bytes'  => $measured['fs_used_bytes'],
                    'free_bytes'  => $measured['fs_free_bytes'],
                    'measured_at' => now()->toIso8601String(),
                ], now()->addHours(26));
            }
        }

        return [
            'site_id'          => $site->id,
            'ok'               => true,
            'error'            => null,
            'disk_usage_bytes' => $measured['used_bytes'],
            'soft_limit_bytes' => $soft,
            'percent'          => $eval['percent'],
            'level'            => $eval['level'],
            'previous_level'   => $previous,
        ];
    }

    /**
     * One round trip: `du` of the web root (allocated KiB, one filesystem;
     * -D follows the root itself when it is a release symlink, nothing below
     * it) followed by the `df` line of the filesystem holding it.
     */
    private function measure($driver, string $root): array
    {
        $timeout = max(5, (int) config('resource_governance.disk.remote_timeout_seconds', 120));
        $q       = PosixShell::quote($root);

        $result = $driver->execute(
            "timeout {$timeout} du -skx -D {$q} 2>/dev/null | cut -f1; df -B1 -P {$q} 2>/dev/null | tail -n 1"
        );

        $lines = array_values(array_filter(array_map('trim', explode("\n", $result->stdout)), fn ($l) => $l !== ''));

        if (!isset($lines[0]) || !ctype_digit($lines[0])) {
            throw new \RuntimeException('اندازه‌گیری فضای دیسک ناموفق بود: ' . mb_substr($result->output(), 0, 200));
        }

        $df = isset($lines[1]) ? preg_split('/\s+/', $lines[1]) : [];
        $fsOk = count($df) >= 4 && ctype_digit($df[1]) && ctype_digit($df[2]) && ctype_digit($df[3]);

        return [
            'used_bytes'     => (int) $lines[0] * 1024,
            'fs_total_bytes' => $fsOk ? (int) $df[1] : null,
            'fs_used_bytes'  => $fsOk ? (int) $df[2] : null,
            'fs_free_bytes'  => $fsOk ? (int) $df[3] : null,
        ];
    }

    // ─── Scheduled governance ────────────────────────────────────────────────

    public function govern(Site $site, bool $dryRun = false): array
    {
        $audit = $this->audit($site, !$dryRun);

        $summary = [
            'site_id' => $site->id,
            'audit'   => $audit,
            'action'  => 'none',
            'prune'   => null,
        ];

        if (!$audit['ok']) {
            $summary['action'] = 'unreachable';
            return $summary;
        }

        /** @var DangerLevel $level */
        $level   = $audit['level'];
        $profile = match ($level) {
            DangerLevel::Warning  => 'routine',
            DangerLevel::Critical => 'aggressive',
            default               => null,
        };

        if ($profile === null) {
            return $summary;
        }

        if ($dryRun) {
            $summary['action'] = "would_prune:{$profile}";
            return $summary;
        }

        if ($level === DangerLevel::Critical && $audit['previous_level']->severity() < DangerLevel::Critical->severity()) {
            $this->notifyCritical($site, $audit);
        }

        $execute = (bool) config('resource_governance.prune.auto_enabled', false);

        try {
            $log = $execute
                ? $this->pruner->execute($site, $profile, TenantPruneAuditLog::TRIGGER_AUTO)
                : $this->pruner->preview($site, $profile, TenantPruneAuditLog::TRIGGER_AUTO);

            $summary['action'] = ($execute ? 'pruned:' : 'previewed:') . $profile;
            $summary['prune']  = $log->only(['id', 'mode', 'status', 'reclaimed_bytes', 'pruned_items_count']);

            if ($execute && $log->status !== TenantPruneAuditLog::STATUS_FAILED) {
                // Re-measure so the dashboard reflects the reclaimed space.
                $summary['audit'] = $this->audit($site);
            }
        } catch (PruneInProgressException) {
            $summary['action'] = 'skipped:prune_in_progress';
        }

        return $summary;
    }

    private function notifyCritical(Site $site, array $audit): void
    {
        try {
            app(NotificationCenterService::class)->notify([
                'type'          => 'resource_critical',
                'priority'      => 'critical',
                'title'         => "🔴 فضای دیسک بحرانی: {$site->title}",
                'message'       => "مصرف دیسک سایت {$site->title} به " . digits_fa((string) $audit['percent']) . '٪ سهمیه رسیده است و نیاز به بررسی فوری دارد.',
                'audience_type' => 'platform_admin',
                'channel'       => 'dashboard',
                'site_id'       => $site->id,
                'link'          => route('admin.devops.resources.index', ['q' => $site->domain ?: $site->title]),
            ]);
        } catch (\Throwable $e) {
            Log::warning('devops: critical disk notification failed', ['site_id' => $site->id, 'error' => $e->getMessage()]);
        }
    }

    // ─── Dashboard data ──────────────────────────────────────────────────────

    /**
     * One row per non-deleted site, with its effective quota and latest
     * measurement. Limits are loaded in bulk (no per-site queries).
     */
    public function tenantMatrix(?string $search = null): array
    {
        $sites = Site::query()
            ->where(fn ($q) => $q->whereNull('lifecycle_status')->orWhere('lifecycle_status', '!=', 'deleted'))
            ->when($search !== null && trim($search) !== '', function ($q) use ($search) {
                $term = '%' . addcslashes(trim($search), '%_\\') . '%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('domain', 'like', $term));
            })
            ->orderBy('id')
            ->get(['id', 'title', 'domain', 'lifecycle_status']);

        $ids       = $sites->pluck('id');
        $usages    = SiteResourceUsage::whereIn('site_id', $ids)->get()->keyBy('site_id');
        $overrides = SiteResourceLimit::whereIn('site_id', $ids)->get()->keyBy('site_id');
        $plan      = SiteResourceLimit::whereNull('site_id')
            ->where('plan_name', config('resource_governance.default_plan', 'basic'))
            ->first();
        $servers   = $this->serverIdsBySite($ids->all());
        $lastPrune = TenantPruneAuditLog::whereIn('id', TenantPruneAuditLog::whereIn('site_id', $ids)
                ->selectRaw('MAX(id)')->groupBy('site_id'))
            ->get()->keyBy('site_id');

        return $sites->map(function (Site $site) use ($usages, $overrides, $plan, $servers, $lastPrune) {
            $usage = $usages->get($site->id);
            $limit = $overrides->get($site->id) ?? $plan ?? new SiteResourceLimit();
            $soft  = $limit->effectiveDiskSoftLimit();
            $used  = $usage?->disk_usage_bytes !== null ? (int) $usage->disk_usage_bytes : null;
            $eval  = $this->evaluate($used, $soft);
            $prune = $lastPrune->get($site->id);

            return [
                'site_id'          => $site->id,
                'title'            => (string) $site->title,
                'domain'           => (string) $site->domain,
                'server_id'        => $servers[$site->id] ?? null,
                'disk_usage_bytes' => $used,
                'soft_limit_bytes' => $soft,
                'hard_limit_bytes' => $limit->effectiveDiskHardLimit(),
                'cpu_cores_limit'  => $limit->cpu_cores_limit !== null ? (float) $limit->cpu_cores_limit : null,
                'memory_limit_bytes' => $limit->memory_limit_bytes !== null ? (int) $limit->memory_limit_bytes : null,
                'percent'          => $eval['percent'],
                'danger_level'     => $eval['level']->value,
                'danger_label'     => $eval['level']->label(),
                'audited_at'       => $usage?->disk_audited_at?->toIso8601String(),
                'audit_error'      => $usage?->disk_audit_error,
                'last_prune'       => $prune ? [
                    'id'              => $prune->id,
                    'mode'            => $prune->mode,
                    'status'          => $prune->status,
                    'reclaimed_bytes' => $prune->reclaimed_bytes,
                    'at'              => $prune->created_at?->toIso8601String(),
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * Thin-provisioning view: allocated soft quota vs. the physical disk of
     * the servers hosting those sites (from the latest audit's `df`).
     */
    public function poolSummary(array $matrix): array
    {
        $byServer = [];
        foreach ($matrix as $row) {
            if ($row['server_id'] === null) {
                continue;
            }
            $byServer[$row['server_id']]['allocated'] = ($byServer[$row['server_id']]['allocated'] ?? 0) + $row['soft_limit_bytes'];
            $byServer[$row['server_id']]['consumed']  = ($byServer[$row['server_id']]['consumed'] ?? 0) + (int) $row['disk_usage_bytes'];
        }

        $physical = $used = $free = 0;
        $measuredServers = 0;
        $allocatedOnMeasured = 0;

        foreach ($byServer as $serverId => $agg) {
            $pool = Cache::get(self::POOL_CACHE_PREFIX . $serverId);
            if (!is_array($pool) || empty($pool['total_bytes'])) {
                continue;
            }
            $measuredServers++;
            $physical += (int) $pool['total_bytes'];
            $used     += (int) $pool['used_bytes'];
            $free     += (int) $pool['free_bytes'];
            $allocatedOnMeasured += $agg['allocated'];
        }

        $levels = array_count_values(array_column($matrix, 'danger_level'));

        return [
            'sites_total'          => count($matrix),
            'servers_total'        => count($byServer),
            'servers_measured'     => $measuredServers,
            'allocated_bytes'      => array_sum(array_column($matrix, 'soft_limit_bytes')),
            'consumed_bytes'       => array_sum(array_map(fn ($r) => (int) $r['disk_usage_bytes'], $matrix)),
            'physical_bytes'       => $measuredServers ? $physical : null,
            'physical_used_bytes'  => $measuredServers ? $used : null,
            'physical_free_bytes'  => $measuredServers ? $free : null,
            // > 1.0 means more quota has been promised than the disks hold.
            'overcommit_ratio'     => $physical > 0 ? round($allocatedOnMeasured / $physical, 2) : null,
            'levels'               => [
                'normal'   => $levels['normal'] ?? 0,
                'warning'  => $levels['warning'] ?? 0,
                'critical' => $levels['critical'] ?? 0,
                'unknown'  => $levels['unknown'] ?? 0,
            ],
        ];
    }

    /** @return array<int, int> site_id => remote_server_id of the latest completed install */
    private function serverIdsBySite(array $siteIds): array
    {
        if ($siteIds === []) {
            return [];
        }

        $latest = CustomerInstallation::whereIn('site_id', $siteIds)
            ->where('status', 'complete')
            ->whereNotNull('remote_server_id')
            ->select('site_id', DB::raw('MAX(id) as id'))
            ->groupBy('site_id');

        return CustomerInstallation::whereIn('id', $latest->pluck('id'))
            ->pluck('remote_server_id', 'site_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    // ─── Quotas & authorization ──────────────────────────────────────────────

    /**
     * Update only the DevOps quota fields. A missing per-site row is seeded
     * from the site's current effective limits, so creating it never silently
     * replaces plan values with column defaults.
     */
    public function updateQuota(Site $site, array $data): SiteResourceLimit
    {
        $limit = SiteResourceLimit::where('site_id', $site->id)->first();

        if (!$limit) {
            $effective = SiteResourceLimit::defaultForSite($site);
            $limit = new SiteResourceLimit(array_merge(
                array_intersect_key($effective->getAttributes(), array_flip($effective->getFillable())),
                ['site_id' => $site->id, 'plan_name' => null]
            ));
        }

        $limit->fill([
            'disk_soft_limit_bytes' => $data['disk_soft_limit_bytes'],
            'disk_hard_limit_bytes' => $data['disk_hard_limit_bytes'] ?? null,
            'cpu_cores_limit'       => $data['cpu_cores_limit'] ?? null,
            'memory_limit_bytes'    => $data['memory_limit_bytes'] ?? null,
        ]);
        $limit->save();

        // Re-classify the stored measurement against the new quota right away.
        $usage = SiteResourceUsage::where('site_id', $site->id)->first();
        if ($usage && $usage->disk_usage_bytes !== null) {
            $usage->update([
                'danger_level' => $this->evaluate((int) $usage->disk_usage_bytes, $limit->effectiveDiskSoftLimit())['level']->value,
            ]);
        }

        return $limit;
    }

    public const PRUNE_PERMISSION = 'devops.storage.prune';

    /**
     * Deleting files on customer servers is narrower than admin access
     * (same boundary style as tenant.upgrade.execute): super admins, or a
     * role explicitly granted devops.storage.prune. Legacy is_admin-only
     * users can view and edit quotas but cannot prune.
     */
    public static function canPrune(?User $user): bool
    {
        return $user !== null && app(AuthorizationService::class)->can($user, self::PRUNE_PERMISSION);
    }
}
