<?php
namespace App\Services\Lifecycle;

use App\Models\Site;
use App\Models\SiteLifecycleLog;
use App\Models\SiteLifecycleInvoice;
use Illuminate\Support\Facades\Log;

/**
 * State machine for customer site lifecycle.
 *
 * Valid transitions:
 *   active        → grace_period | suspended | archived
 *   grace_period  → active | suspended
 *   suspended     → active | archived
 *   archived      → active (restore)
 *   archived      → deleted
 */
class SiteLifecycleService
{
    private static array $allowedTransitions = [
        'active'       => ['grace_period', 'suspended', 'archived'],
        'grace_period' => ['active', 'suspended'],
        'suspended'    => ['active', 'archived'],
        'archived'     => ['active', 'deleted'],
        'deleted'      => [],
    ];

    public function __construct(
        private SiteLifecycleNotificationService $notifications,
        private SiteArchiveService $archive
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    public function activate(Site $site, string $reason = 'فعال‌سازی', ?int $adminId = null): void
    {
        $this->transition($site, Site::LIFECYCLE_ACTIVE, $reason, $adminId);
        $site->is_active = true;
        $site->save();
    }

    public function startGracePeriod(Site $site, string $reason = 'انقضای اشتراک', ?int $adminId = null): void
    {
        $graceDays = (int) config('lifecycle.grace_period_days', 15);
        $site->grace_period_started_at = now();
        $site->grace_period_ends_at    = now()->addDays($graceDays);
        $site->save();

        $this->transition($site, Site::LIFECYCLE_GRACE_PERIOD, $reason, $adminId);
        $this->notifications->gracePeriodStarted($site);
    }

    public function suspend(Site $site, string $reason = 'تعلیق', ?int $adminId = null): void
    {
        $site->suspended_at     = now();
        $site->suspended_reason = $reason;
        $site->is_active        = false;
        $site->save();

        $this->transition($site, Site::LIFECYCLE_SUSPENDED, $reason, $adminId);
        $this->notifications->siteSuspended($site);
    }

    public function archive(Site $site, string $reason = 'آرشیو خودکار پس از تعلیق', ?int $adminId = null): void
    {
        $site->archived_at = now();
        $site->is_active   = false;
        $site->save();

        $this->transition($site, Site::LIFECYCLE_ARCHIVED, $reason, $adminId);

        $archiveService = app(SiteArchiveService::class);

        try {
            $archiveService->createArchivePackage($site);
        } catch (\Throwable $e) {
            Log::error("SiteLifecycleService: archive package failed for site {$site->id}", [
                'error' => $e->getMessage(),
            ]);
        }

        // C05 — Physical file deletion after ZIP is created
        // Only delete if ZIP was created successfully (archive_path set)
        try {
            if ($site->archive_path) {
                $freed = $archiveService->deleteActiveFiles($site);
                Log::info("SiteLifecycleService: C05 freed {$freed} bytes for site {$site->id}");
            } else {
                Log::warning("SiteLifecycleService: C05 skipped file deletion — ZIP not confirmed for site {$site->id}");
            }
        } catch (\Throwable $e) {
            Log::error("SiteLifecycleService: C05 file deletion failed for site {$site->id}", [
                'error' => $e->getMessage(),
            ]);
        }

        // Release active resource usage — keep archive_bytes only
        try {
            app(\App\Services\ResourceGovernance\SiteResourceCalculatorService::class)
                ->releaseActiveResources($site);
        } catch (\Throwable $e) {
            Log::warning("SiteLifecycleService: resource release failed for site {$site->id}", [
                'error' => $e->getMessage(),
            ]);
        }

        $this->notifications->siteArchived($site);
    }

    public function requestRestore(Site $site, ?int $adminId = null): SiteLifecycleInvoice
    {
        $site->restore_requested_at = now();
        $site->save();

        return $this->createRestoreInvoice($site);
    }

    public function restore(Site $site, string $reason = 'بازگردانی سایت', ?int $adminId = null): void
    {
        $site->archived_at          = null;
        $site->suspended_at         = null;
        $site->suspended_reason     = null;
        $site->restore_requested_at = null;
        $site->archive_path         = null;
        $site->grace_period_started_at = null;
        $site->grace_period_ends_at    = null;
        $site->is_active            = true;
        $site->save();

        $this->transition($site, Site::LIFECYCLE_ACTIVE, $reason, $adminId);
        $this->notifications->siteRestored($site);
    }

    public function delete(Site $site, string $reason = 'حذف پس از انقضای نگهداری', ?int $adminId = null): void
    {
        $site->lifecycle_deleted_at = now();
        $site->is_active            = false;
        $site->save();

        $this->transition($site, Site::LIFECYCLE_DELETED, $reason, $adminId);

        // GAP-M10 — Physical cleanup: delete the archive ZIP from disk
        $this->archive->deleteArchiveZip($site);

        Log::warning("SiteLifecycleService: site {$site->id} ({$site->domain}) marked as DELETED");
    }

    // ── Invoice ──────────────────────────────────────────────────────────────

    public function createRestoreInvoice(Site $site): SiteLifecycleInvoice
    {
        $reactivationFee = (int) config('lifecycle.reactivation_fee', 0);
        $restoreFee      = (int) config('lifecycle.archive_restore_fee', 0);
        $storageFee      = (int) config('lifecycle.storage_retention_fee', 0);
        $total           = $reactivationFee + $restoreFee + $storageFee;

        $lineItems = [];
        if ($reactivationFee > 0) {
            $lineItems[] = ['label' => 'هزینه فعال‌سازی مجدد', 'amount' => $reactivationFee];
        }
        if ($restoreFee > 0) {
            $lineItems[] = ['label' => 'هزینه بازگردانی آرشیو', 'amount' => $restoreFee];
        }
        if ($storageFee > 0) {
            $lineItems[] = ['label' => 'هزینه نگهداری', 'amount' => $storageFee];
        }

        return SiteLifecycleInvoice::create([
            'site_id'        => $site->id,
            'invoice_number' => SiteLifecycleInvoice::generateNumber(),
            'type'           => SiteLifecycleInvoice::TYPE_REACTIVATION,
            'amount'         => $total,
            'status'         => SiteLifecycleInvoice::STATUS_PENDING,
            'due_at'         => now()->addDays(7),
            'line_items'     => $lineItems,
        ]);
    }

    public function markInvoicePaid(SiteLifecycleInvoice $invoice, string $reference): void
    {
        $invoice->status            = SiteLifecycleInvoice::STATUS_PAID;
        $invoice->paid_at           = now();
        $invoice->payment_reference = $reference;
        $invoice->save();
    }

    // ── Core Transition ───────────────────────────────────────────────────────

    public function transition(Site $site, string $toStatus, ?string $reason = null, ?int $changedById = null): void
    {
        $fromStatus = $site->getLifecycleStatus();

        if ($fromStatus === $toStatus) {
            return;
        }

        $allowed = self::$allowedTransitions[$fromStatus] ?? [];
        if (! in_array($toStatus, $allowed, true)) {
            Log::warning("SiteLifecycleService: illegal transition {$fromStatus}→{$toStatus} for site {$site->id}");
            return;
        }

        $site->lifecycle_status     = $toStatus;
        $site->lifecycle_changed_at = now();
        $site->save();

        SiteLifecycleLog::create([
            'site_id'    => $site->id,
            'from_status'=> $fromStatus,
            'to_status'  => $toStatus,
            'reason'     => $reason,
            'changed_by' => $changedById,
            'is_system'  => $changedById === null,
            'metadata'   => ['timestamp' => now()->toIso8601String()],
        ]);

        $this->pushStatusToCustomerSite($site, $toStatus);

        Log::info("SiteLifecycleService: site {$site->id} → {$toStatus}", [
            'from'   => $fromStatus,
            'reason' => $reason,
        ]);
    }

    // ── Queries ──────────────────────────────────────────────────────────────

    public function sitesEnteringGracePeriod(): \Illuminate\Database\Eloquent\Collection
    {
        return Site::where('lifecycle_status', Site::LIFECYCLE_ACTIVE)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('host_expires_at', '<', now())
                  ->orWhere('support_expires_at', '<', now());
            })
            ->get();
    }

    public function sitesEndingGracePeriod(): \Illuminate\Database\Eloquent\Collection
    {
        return Site::where('lifecycle_status', Site::LIFECYCLE_GRACE_PERIOD)
            ->where('grace_period_ends_at', '<=', now())
            ->get();
    }

    public function sitesReadyToArchive(): \Illuminate\Database\Eloquent\Collection
    {
        $archiveDays = (int) config('lifecycle.archive_after_days', 90);
        $cutoff = now()->subDays($archiveDays);

        return Site::where('lifecycle_status', Site::LIFECYCLE_SUSPENDED)
            ->where('suspended_at', '<=', $cutoff)
            ->get();
    }

    /**
     * Push lifecycle_status to the customer-site's settings table via dynamic DB connection.
     * This allows the customer-site middleware to enforce lifecycle state without an API call.
     */
    private function pushStatusToCustomerSite(Site $site, string $status): void
    {
        if (! $site->db_host || ! $site->db_name) {
            return;
        }

        try {
            $connKey = 'lifecycle_push_' . $site->id;
            \Illuminate\Support\Facades\Config::set("database.connections.{$connKey}", [
                'driver'    => 'mysql',
                'host'      => $site->db_host,
                'port'      => '3306',
                'database'  => $site->db_name,
                'username'  => $site->db_user,
                'password'  => $site->db_password ?? '',
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix'    => '',
                'strict'    => false,
            ]);

            \Illuminate\Support\Facades\DB::purge($connKey);
            $conn = \Illuminate\Support\Facades\DB::connection($connKey);

            if (! $conn->getSchemaBuilder()->hasTable('settings')) {
                return;
            }

            $now = now()->toDateTimeString();
            $existing = $conn->table('settings')->where('key', 'site_lifecycle_status')->first();

            if ($existing) {
                $conn->table('settings')->where('key', 'site_lifecycle_status')->update([
                    'value'      => $status,
                    'updated_at' => $now,
                ]);
            } else {
                $conn->table('settings')->insert([
                    'key'        => 'site_lifecycle_status',
                    'value'      => $status,
                    'group'      => 'lifecycle',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Also clear the settings cache in the customer site
            $conn->table('cache')->where('key', 'settings')->delete();

        } catch (\Throwable $e) {
            Log::warning("SiteLifecycleService: could not push status to customer site {$site->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sitesReadyToDelete(): \Illuminate\Database\Eloquent\Collection
    {
        $deleteDays = (int) config('lifecycle.delete_after_days', 365);
        $cutoff = now()->subDays($deleteDays);

        return Site::where('lifecycle_status', Site::LIFECYCLE_ARCHIVED)
            ->where('archived_at', '<=', $cutoff)
            ->whereNull('restore_requested_at')
            ->get();
    }
}
