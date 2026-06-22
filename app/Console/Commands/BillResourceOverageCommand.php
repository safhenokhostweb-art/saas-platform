<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\SiteLifecycleInvoice;
use App\Models\SiteResourceLimit;
use App\Models\SiteResourceUsage;
use App\Services\Lifecycle\SiteLifecycleNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * GAP-H04 — Bill Resource Overage
 *
 * Runs monthly. For each active site:
 *  1. Loads resource usage + limit
 *  2. Calculates storage overage (usage - limit, in GB)
 *  3. If overage fee is configured and site is over limit, creates a SiteLifecycleInvoice (TYPE_OVERAGE)
 *  4. Sends SMS notification to site admin
 *  5. Idempotent: skips if an overage invoice already exists this month
 */
class BillResourceOverageCommand extends Command
{
    protected $signature   = 'resource:bill-overage
                              {--dry-run : نمایش بدون صدور فاکتور}
                              {--site= : پردازش فقط یک سایت مشخص}';
    protected $description = 'GAP-H04: صدور فاکتور ماهانه مازاد فضای ذخیره‌سازی برای سایت‌های متجاوز از محدودیت';

    public function __construct(private SiteLifecycleNotificationService $notif)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $siteId = $this->option('site');

        $query = Site::whereIn('lifecycle_status', ['active', 'grace_period']);
        if ($siteId) {
            $query->where('id', $siteId);
        }

        $sites   = $query->get();
        $billed  = 0;
        $skipped = 0;
        $errors  = 0;

        $this->info("resource:bill-overage — {$sites->count()} سایت" . ($dryRun ? ' [dry-run]' : ''));

        foreach ($sites as $site) {
            try {
                $usage = SiteResourceUsage::where('site_id', $site->id)->first();
                $limit = SiteResourceLimit::defaultForSite($site);

                if (! $usage || ! $limit->storageOverageActive()) {
                    $skipped++;
                    continue;
                }

                $limitBytes   = (int) $limit->storage_limit_bytes;
                $storageBytes = (int) $usage->storage_bytes;

                if ($storageBytes <= $limitBytes) {
                    $skipped++;
                    continue;
                }

                $overageBytes = $storageBytes - $limitBytes;
                $overageGb    = $overageBytes / (1024 ** 3);
                $amountRial   = (int) round($overageGb * (float) $limit->storage_overage_fee_per_gb);

                if ($amountRial <= 0) {
                    $skipped++;
                    continue;
                }

                // Idempotency: skip if overage invoice already created this month
                $alreadyBilled = SiteLifecycleInvoice::where('site_id', $site->id)
                    ->where('type', SiteLifecycleInvoice::TYPE_OVERAGE)
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->exists();

                if ($alreadyBilled) {
                    $this->line("  ← [{$site->id}] {$site->title} — فاکتور این ماه قبلاً صادر شده");
                    $skipped++;
                    continue;
                }

                $overageFormatted = round($overageGb, 2);

                if ($dryRun) {
                    $this->line("[dry-run] [{$site->id}] {$site->title} — {$overageFormatted} GB مازاد — " . number_format($amountRial) . " ریال");
                    $billed++;
                    continue;
                }

                // Create invoice
                $invoice = SiteLifecycleInvoice::create([
                    'site_id'        => $site->id,
                    'invoice_number' => SiteLifecycleInvoice::generateNumber(),
                    'type'           => SiteLifecycleInvoice::TYPE_OVERAGE,
                    'amount'         => $amountRial,
                    'status'         => SiteLifecycleInvoice::STATUS_PENDING,
                    'due_at'         => now()->addDays(14),
                    'line_items'     => [
                        [
                            'description' => "مازاد فضای ذخیره‌سازی: {$overageFormatted} GB",
                            'unit'        => 'ریال/GB',
                            'quantity'    => $overageFormatted,
                            'unit_price'  => (int) $limit->storage_overage_fee_per_gb,
                            'total'       => $amountRial,
                        ],
                    ],
                ]);

                // Send SMS to site admin
                try {
                    $this->notif->sendOverageInvoiceSms($site, $invoice->invoice_number, $amountRial);
                } catch (\Throwable $smsErr) {
                    Log::warning("GAP-H04: overage SMS failed for site {$site->id}", ['error' => $smsErr->getMessage()]);
                }

                $this->line("  ✓ [{$site->id}] {$site->title} — {$overageFormatted} GB — " . number_format($amountRial) . " ریال — #{$invoice->invoice_number}");
                $billed++;

                Log::info('GAP-H04: overage invoice created', [
                    'site_id'  => $site->id,
                    'invoice'  => $invoice->invoice_number,
                    'amount'   => $amountRial,
                    'overage'  => $overageFormatted . 'GB',
                ]);
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("  ✗ [{$site->id}] {$site->title}: {$e->getMessage()}");
                Log::error("GAP-H04: overage billing error for site {$site->id}", ['error' => $e->getMessage()]);
            }
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}فاکتور صادر: {$billed} | رد شده: {$skipped} | خطا: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
