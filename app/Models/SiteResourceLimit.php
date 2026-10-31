<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteResourceLimit extends Model
{
    protected $table = 'site_resource_limits';

    protected $fillable = [
        'site_id',
        'plan_name',
        'storage_limit_bytes',
        'bandwidth_limit_bytes',
        'disk_soft_limit_bytes',
        'disk_hard_limit_bytes',
        'archive_retention_days',
        'backup_retention_days',
        'cpu_weight',
        'ram_weight',
        'cpu_cores_limit',
        'memory_limit_bytes',
        'storage_overage_fee_per_gb',
        'bandwidth_overage_fee_per_gb',
        'archive_retention_fee',
        'restore_fee',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function formattedStorageLimit(): string
    {
        return SiteResourceUsage::formatBytes((int) $this->storage_limit_bytes);
    }

    public function formattedBandwidthLimit(): string
    {
        return SiteResourceUsage::formatBytes((int) $this->bandwidth_limit_bytes);
    }

    /** Disk soft quota for the measured web root; config default when unset. */
    public function effectiveDiskSoftLimit(): int
    {
        return (int) ($this->disk_soft_limit_bytes ?: config('resource_governance.disk.soft_limit_bytes', 5368709120));
    }

    /** Disk hard quota, or 0 when none is configured. */
    public function effectiveDiskHardLimit(): int
    {
        return (int) ($this->disk_hard_limit_bytes ?: config('resource_governance.disk.hard_limit_bytes', 0));
    }

    public function storageOverageActive(): bool
    {
        return $this->storage_overage_fee_per_gb > 0;
    }

    public function bandwidthOverageActive(): bool
    {
        return $this->bandwidth_overage_fee_per_gb > 0;
    }

    public static function defaultForSite(Site $site): self
    {
        // Try per-site override first
        $override = self::where('site_id', $site->id)->first();
        if ($override) return $override;

        // Try plan-level default
        $plan = config('resource_governance.default_plan', 'basic');
        $planDefault = self::whereNull('site_id')->where('plan_name', $plan)->first();
        if ($planDefault) return $planDefault;

        // Fall back to config defaults
        return new self([
            'storage_limit_bytes'          => config('resource_governance.storage_limit_bytes', 1073741824),
            'bandwidth_limit_bytes'        => config('resource_governance.bandwidth_limit_bytes', 10737418240),
            'archive_retention_days'       => config('resource_governance.archive_retention_days', 365),
            'backup_retention_days'        => config('resource_governance.backup_retention_days', 30),
            'cpu_weight'                   => config('resource_governance.cpu_weight', 10),
            'ram_weight'                   => config('resource_governance.ram_weight', 10),
            'storage_overage_fee_per_gb'   => config('resource_governance.storage_overage_fee_per_gb', 0),
            'bandwidth_overage_fee_per_gb' => config('resource_governance.bandwidth_overage_fee_per_gb', 0),
            'archive_retention_fee'        => config('resource_governance.archive_retention_fee', 0),
            'restore_fee'                  => config('resource_governance.restore_fee', 0),
        ]);
    }
}
