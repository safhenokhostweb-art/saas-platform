<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\SiteResourceUsage;
use App\Models\TenantPruneAuditLog;
use App\Services\DevOps\Exceptions\PruneInProgressException;
use App\Services\DevOps\TenantStoragePruner;
use Illuminate\Console\Command;

class DevOpsPruneTenantCommand extends Command
{
    protected $signature = 'devops:prune-tenant
                            {tenant_id : Site ID}
                            {--dry-run : List what would be removed without deleting anything}
                            {--profile=routine : Retention profile (routine | aggressive)}
                            {--force : Skip the confirmation prompt when deleting}';

    protected $description = 'Audited cleanup of regenerable files (logs, cache, views, sessions, temp, old dumps) in one tenant web root';

    public function handle(TenantStoragePruner $pruner): int
    {
        $id = (string) $this->argument('tenant_id');
        if (!ctype_digit($id)) {
            $this->error('tenant_id باید شناسهٔ عددی سایت باشد.');
            return self::INVALID;
        }

        $profile = (string) $this->option('profile');
        if (!in_array($profile, TenantStoragePruner::profiles(), true)) {
            $this->error('پروفایل نامعتبر است. مقادیر مجاز: ' . implode(', ', TenantStoragePruner::profiles()));
            return self::INVALID;
        }

        $site = Site::find((int) $id);
        if (!$site) {
            $this->error("سایت {$id} یافت نشد.");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (!$dryRun && !$this->option('force') && $this->input->isInteractive()
            && !$this->confirm("فایل‌های قابل پاک‌سازی سایت «{$site->title}» حذف شوند؟ (ابتدا --dry-run را اجرا کنید)")) {
            $this->line('لغو شد.');
            return self::SUCCESS;
        }

        try {
            $log = $dryRun
                ? $pruner->preview($site, $profile, TenantPruneAuditLog::TRIGGER_ADMIN)
                : $pruner->execute($site, $profile, TenantPruneAuditLog::TRIGGER_ADMIN);
        } catch (PruneInProgressException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "گزارش #{$log->id} — وضعیت: {$log->status}");

        $rows = [];
        foreach (($log->details['categories'] ?? []) as $category) {
            $rows[] = [$category['label'], $category['count'], SiteResourceUsage::formatBytes((int) $category['bytes'])];
        }
        if ($rows) {
            $this->table(['دسته', 'تعداد فایل', 'حجم'], $rows);
        }

        $this->line(($dryRun ? 'قابل آزادسازی: ' : 'آزاد شد: ')
            . SiteResourceUsage::formatBytes((int) $log->reclaimed_bytes)
            . " در {$log->pruned_items_count} فایل");

        if ($log->error) {
            $this->warn($log->error);
        }

        return $log->status === TenantPruneAuditLog::STATUS_FAILED ? self::FAILURE : self::SUCCESS;
    }
}
