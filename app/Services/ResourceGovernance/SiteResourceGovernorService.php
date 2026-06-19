<?php

namespace App\Services\ResourceGovernance;

use App\Models\Site;
use App\Models\SiteResourceLimit;
use App\Models\SiteResourceUsage;
use App\Models\SiteLifecycleInvoice;

class SiteResourceGovernorService
{
    /**
     * Get the effective resource limits for a site (per-site override or plan default).
     */
    public function getLimits(Site $site): SiteResourceLimit
    {
        return SiteResourceLimit::defaultForSite($site);
    }

    /**
     * Get or create the resource usage record for a site.
     */
    public function getUsage(Site $site): SiteResourceUsage
    {
        return SiteResourceUsage::firstOrNew(['site_id' => $site->id]);
    }

    /**
     * Return the percentage of storage quota used (0–100+).
     */
    public function storagePercent(Site $site): float
    {
        $usage = $this->getUsage($site);
        $limit = $this->getLimits($site);
        return $usage->storagePercent((int) $limit->storage_limit_bytes);
    }

    /**
     * Return the percentage of monthly bandwidth used.
     */
    public function bandwidthPercent(Site $site): float
    {
        $usage = $this->getUsage($site);
        $limit = $this->getLimits($site);
        return $usage->bandwidthPercent((int) $limit->bandwidth_limit_bytes);
    }

    /**
     * Compute overage fees for a site based on current usage.
     * Returns array of line items with description and amount.
     */
    public function calculateOverageFees(Site $site): array
    {
        $usage = $this->getUsage($site);
        $limit = $this->getLimits($site);
        $items = [];

        // Storage overage
        if ($limit->storage_overage_fee_per_gb > 0 && $usage->storage_bytes > $limit->storage_limit_bytes) {
            $overageBytes = $usage->storage_bytes - $limit->storage_limit_bytes;
            $overageGb    = ceil($overageBytes / 1073741824);
            $fee          = $overageGb * $limit->storage_overage_fee_per_gb;
            $items[]      = [
                'type'   => 'storage_overage',
                'label'  => "اضافه‌مصرف فضا ({$overageGb} گیگ)",
                'amount' => $fee,
            ];
        }

        // Bandwidth overage
        if ($limit->bandwidth_overage_fee_per_gb > 0 && $usage->bandwidth_bytes_monthly > $limit->bandwidth_limit_bytes) {
            $overageBytes = $usage->bandwidth_bytes_monthly - $limit->bandwidth_limit_bytes;
            $overageGb    = ceil($overageBytes / 1073741824);
            $fee          = $overageGb * $limit->bandwidth_overage_fee_per_gb;
            $items[]      = [
                'type'   => 'bandwidth_overage',
                'label'  => "اضافه‌مصرف ترافیک ({$overageGb} گیگ)",
                'amount' => $fee,
            ];
        }

        // Archive retention fee
        if ($limit->archive_retention_fee > 0 && !empty($site->archive_path)) {
            $items[] = [
                'type'   => 'archive_retention',
                'label'  => 'هزینه نگهداری آرشیو',
                'amount' => $limit->archive_retention_fee,
            ];
        }

        return $items;
    }

    /**
     * Total overage amount (sum of all line items).
     */
    public function totalOverageFee(Site $site): int
    {
        return (int) array_sum(array_column($this->calculateOverageFees($site), 'amount'));
    }

    /**
     * Sites with storage usage above the given percent threshold.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function sitesAboveStoragePercent(float $percent)
    {
        $limitBytes = config('resource_governance.storage_limit_bytes', 1073741824);
        $threshold  = (int) ($limitBytes * $percent / 100);

        return SiteResourceUsage::with('site')
            ->where('storage_bytes', '>=', $threshold)
            ->orderByDesc('storage_bytes')
            ->get();
    }

    /**
     * Sites sorted by storage usage descending.
     */
    public function sitesOrderedByStorage(int $limit = 50)
    {
        return SiteResourceUsage::with('site')
            ->orderByDesc('storage_bytes')
            ->limit($limit)
            ->get();
    }

    /**
     * Sites that have archive_bytes > 0 and site is in archived state.
     */
    public function oversizedArchiveSites(int $thresholdBytes = 104857600)
    {
        return SiteResourceUsage::with('site')
            ->where('archive_bytes', '>', $thresholdBytes)
            ->orderByDesc('archive_bytes')
            ->get();
    }

    /**
     * Sites with no resource usage calculated in the last 48 hours.
     */
    public function inactiveSites()
    {
        return SiteResourceUsage::with('site')
            ->where(function ($q) {
                $q->whereNull('calculated_at')
                  ->orWhere('calculated_at', '<', now()->subHours(48));
            })
            ->get();
    }
}
