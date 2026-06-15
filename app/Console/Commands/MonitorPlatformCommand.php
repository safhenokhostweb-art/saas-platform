<?php
namespace App\Console\Commands;

use App\Models\Infrastructure\InfraIncident;
use App\Services\Infrastructure\Monitoring\HealthCheckService;
use App\Services\Infrastructure\Monitoring\InfrastructureErrorCollector;
use App\Models\Infrastructure\InfraSchedulerRun;
use Illuminate\Console\Command;

class MonitorPlatformCommand extends Command
{
    protected $signature   = 'monitor:platform';
    protected $description = 'Run platform-level health checks (DB/Redis/Queue/SMS/AI/Disk/Memory/CPU) and persist results';

    public function __construct(
        private HealthCheckService           $health,
        private InfrastructureErrorCollector $collector
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $run = InfraSchedulerRun::startRun('monitor:platform');

        try {
            $checks = $this->health->checkAndPersist();
            $overall = $this->health->overallStatus($checks);

            $this->handleAlerts($checks);

            $this->info("Platform health: {$overall}");
            $run->endSuccess("Overall: {$overall}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $run->endFailed($e->getMessage());
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function handleAlerts(array $checks): void
    {
        $criticalChecks = [
            'database' => 'database_critical',
            'redis'    => 'redis_critical',
            'disk'     => 'disk_critical',
        ];

        foreach ($criticalChecks as $key => $type) {
            $check = $checks[$key] ?? [];
            if (($check['status'] ?? '') === 'critical') {
                $this->collector->collect(
                    type:     $type,
                    title:    "بحران پلتفرم: {$key}",
                    message:  $check['message'] ?? '',
                    siteId:   null,
                    severity: 'critical'
                );
            } elseif (($check['status'] ?? '') === 'online') {
                // Auto-resolve platform-level incidents when service recovers
                InfraIncident::whereNull('site_id')
                    ->where('type', $type)
                    ->active()
                    ->update(['status' => 'resolved', 'resolved_at' => now()]);
            }
        }

        // Failed jobs alert
        $failedCount = $checks['failed_jobs']['count'] ?? 0;
        if ($failedCount !== null && $failedCount >= 10) {
            $severity = $failedCount >= 50 ? 'critical' : 'high';
            $this->collector->collect(
                type:     'queue_dead',
                title:    "Failed Jobs تجمع یافته: {$failedCount}",
                message:  "{$failedCount} job در صف failed_jobs انباشته شده",
                siteId:   null,
                severity: $severity
            );
        }

        // SMS provider alert
        if (($checks['sms']['status'] ?? '') === 'critical') {
            $this->collector->collect(
                type:     'sms_failure',
                title:    'سرویس SMS در دسترس نیست',
                message:  $checks['sms']['message'] ?? '',
                siteId:   null,
                severity: 'high'
            );
        }

        // AI provider circuit-breaker open
        if (($checks['ai']['status'] ?? '') === 'critical') {
            $this->collector->collect(
                type:     'ai_failure',
                title:    'سرویس AI Circuit Breaker OPEN',
                message:  $checks['ai']['message'] ?? '',
                siteId:   null,
                severity: 'high'
            );
        }
    }
}
