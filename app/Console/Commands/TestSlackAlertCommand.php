<?php
namespace App\Console\Commands;

use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Console\Command;

class TestSlackAlertCommand extends Command
{
    protected $signature   = 'slack:test {--level=info : Severity level (critical|high|warning|info)}';
    protected $description = 'Send a test alert to the configured Slack webhook';

    public function handle(SlackAlertService $slack): int
    {
        $level = $this->option('level');

        if (!in_array($level, ['critical', 'high', 'warning', 'info'])) {
            $this->error("Invalid level. Use: critical | high | warning | info");
            return self::FAILURE;
        }

        $webhook = config('services.slack_alert.webhook');
        if (empty($webhook)) {
            $this->warn('SLACK_ALERT_WEBHOOK is not configured. Add it to .env to enable alerts.');
            return self::FAILURE;
        }

        $slack->$level(
            'Test Alert — ' . config('app.name'),
            "This is a test alert from the `slack:test` Artisan command.",
            ['triggered_by' => 'manual test', 'command' => 'php artisan slack:test']
        );

        $this->info("Test alert ({$level}) sent to Slack channel: " . config('services.slack_alert.channel'));
        return self::SUCCESS;
    }
}
