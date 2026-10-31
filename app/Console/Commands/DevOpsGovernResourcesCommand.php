<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\DevOps\TenantResourceGovernor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DevOpsGovernResourcesCommand extends Command
{
    protected $signature = 'devops:govern-resources
                            {--site= : Process only this site ID}
                            {--dry-run : Measure and report only; persist nothing and prune nothing}';

    protected $aliases = ['devops:resources:govern'];

    protected $description = 'Measure tenant web-root disk usage, classify danger zones, and run the matching prune profile';

    public function handle(TenantResourceGovernor $governor): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $siteId = $this->option('site');

        if ($siteId !== null && !ctype_digit((string) $siteId)) {
            $this->error('--site باید شناسهٔ عددی سایت باشد.');
            return self::INVALID;
        }

        $query = Site::query()->where(fn ($q) => $q->whereNull('lifecycle_status')->orWhere('lifecycle_status', '!=', 'deleted'));
        if ($siteId !== null) {
            $query->whereKey((int) $siteId);
        }

        $sites = $query->orderBy('id')->get();
        if ($siteId !== null && $sites->isEmpty()) {
            $this->error("سایت {$siteId} یافت نشد.");
            return self::FAILURE;
        }

        $auto = (bool) config('resource_governance.prune.auto_enabled', false);
        $this->info("devops:govern-resources — {$sites->count()} سایت"
            . ($dryRun ? ' [dry-run]' : '')
            . ($auto ? '' : ' [auto-prune off: previews only]'));

        $stats = ['normal' => 0, 'warning' => 0, 'critical' => 0, 'unreachable' => 0, 'errors' => 0];

        foreach ($sites as $site) {
            try {
                $result = $governor->govern($site, $dryRun);
                $audit  = $result['audit'];

                if (!$audit['ok']) {
                    $stats['unreachable']++;
                    $this->line("  – [{$site->id}] {$site->title}: قابل اندازه‌گیری نیست ({$audit['error']})");
                    continue;
                }

                $stats[$audit['level']->value] = ($stats[$audit['level']->value] ?? 0) + 1;
                $this->line(sprintf(
                    '  %s [%d] %s — %s%% (%s) → %s',
                    $audit['level']->value === 'normal' ? '✓' : '⚠',
                    $site->id,
                    $site->title,
                    $audit['percent'] ?? '?',
                    $audit['level']->label(),
                    $result['action']
                ));
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn("  ✗ [{$site->id}] {$site->title}: {$e->getMessage()}");
                Log::warning('devops:govern-resources error', ['site_id' => $site->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info(sprintf(
            'کامل شد: عادی %d، هشدار %d، بحرانی %d، غیرقابل‌دسترس %d، خطا %d',
            $stats['normal'], $stats['warning'], $stats['critical'], $stats['unreachable'], $stats['errors']
        ));
        Log::info('devops:govern-resources complete', $stats + ['dry_run' => $dryRun]);

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
