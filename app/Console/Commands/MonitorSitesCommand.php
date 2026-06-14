<?php
namespace App\Console\Commands;

use App\Models\Site;
use App\Models\Infrastructure\InfraIncident;
use App\Services\Infrastructure\Monitoring\SiteHealthChecker;
use App\Services\Infrastructure\Monitoring\InfrastructureErrorCollector;
use App\Services\Notification\NotificationCenterService;
use Illuminate\Console\Command;

class MonitorSitesCommand extends Command
{
    protected $signature   = 'monitor:sites {--site= : Check a single site by ID}';
    protected $description = 'Run health checks on all active customer sites and detect incidents';

    public function __construct(
        private SiteHealthChecker            $checker,
        private InfrastructureErrorCollector $collector
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $siteId = $this->option('site');
        $sites  = $siteId
            ? Site::where('id', $siteId)->where('is_active', true)->get()
            : Site::where('is_active', true)->get();

        if ($sites->isEmpty()) {
            $this->info('No active sites to check.');
            return self::SUCCESS;
        }

        $this->info("Checking {$sites->count()} site(s)...");

        $healthy  = 0;
        $warnings = 0;
        $critical = 0;

        foreach ($sites as $site) {
            $log = $this->checker->check($site);

            match ($log->status) {
                'healthy'  => $healthy++,
                'warning'  => $warnings++,
                'critical' => $critical++,
                default    => null,
            };

            $this->handleIncidents($log, $site);

            $statusIcon = match ($log->status) {
                'healthy'  => '✓',
                'warning'  => '!',
                'critical' => '✗',
                default    => '?',
            };

            $this->line("  [{$statusIcon}] Site {$site->id} ({$log->domain}): {$log->status} — {$log->response_time_ms}ms");
        }

        $this->info("Done. Healthy: {$healthy} | Warning: {$warnings} | Critical: {$critical}");

        return self::SUCCESS;
    }

    private function handleIncidents($log, Site $site): void
    {
        if ($log->status === 'critical') {
            $this->collector->collect(
                type:     'site_down',
                title:    "سایت در دسترس نیست: {$log->domain}",
                message:  $log->error_message ?? "HTTP {$log->http_status} — سایت پاسخگو نیست",
                siteId:   $log->site_id,
                severity: 'critical'
            );
            try {
                app(NotificationCenterService::class)->siteDown(
                    $site,
                    $log->error_message ?? "HTTP {$log->http_status} — سایت پاسخگو نیست"
                );
            } catch (\Throwable) {}
        } else {
            $wasDown = InfraIncident::where('site_id', $log->site_id)
                ->where('type', 'site_down')
                ->whereNull('resolved_at')
                ->exists();

            $this->collector->resolveForSite((int) $log->site_id, 'site_down');

            if ($wasDown) {
                try {
                    app(NotificationCenterService::class)->siteRecovery($site);
                } catch (\Throwable) {}
            }
        }

        if ($log->db_status === 'error') {
            $this->collector->collect(
                type:     'db_error',
                title:    "خطای دیتابیس در سایت: {$log->domain}",
                message:  'endpoint /api/health گزارش خطای دیتابیس داد',
                siteId:   $log->site_id,
                severity: 'critical'
            );
        } elseif ($log->db_status === 'ok') {
            $this->collector->resolveForSite((int) $log->site_id, 'db_error');
        }

        if ($log->redis_status === 'error') {
            $this->collector->collect(
                type:     'redis_error',
                title:    "خطای Redis در سایت: {$log->domain}",
                message:  'endpoint /api/health گزارش خطای Redis داد',
                siteId:   $log->site_id,
                severity: 'critical'
            );
        } elseif ($log->redis_status === 'ok') {
            $this->collector->resolveForSite((int) $log->site_id, 'redis_error');
        }

        if (in_array($log->ssl_status, ['critical', 'invalid'], true)) {
            $days = $log->ssl_days_remaining ?? 0;
            $this->collector->collect(
                type:     'ssl_expiring',
                title:    "SSL بحرانی: {$log->domain}",
                message:  "گواهی SSL {$days} روز دیگر منقضی می‌شود",
                siteId:   $log->site_id,
                severity: 'critical'
            );
        } elseif ($log->ssl_status === 'expiring_soon') {
            $days = $log->ssl_days_remaining ?? 0;
            $this->collector->collect(
                type:     'ssl_expiring',
                title:    "هشدار SSL: {$log->domain}",
                message:  "گواهی SSL {$days} روز دیگر منقضی می‌شود",
                siteId:   $log->site_id,
                severity: 'warning'
            );
        } elseif ($log->ssl_status === 'valid') {
            $this->collector->resolveForSite((int) $log->site_id, 'ssl_expiring');
        }
    }
}
