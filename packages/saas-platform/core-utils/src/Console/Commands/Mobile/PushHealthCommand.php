<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Mobile;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\MobileRuntime\Notifications\PushTransportValidator;
use SaaSPlatform\CoreUtils\MobileRuntime\Notifications\NotificationDeliveryHealthService;

class PushHealthCommand extends Command
{
    protected $signature = 'mobile:push-health
        {--json             : Output as JSON}
        {--strict           : Treat warnings as failures}
        {--fail-on-warning  : Exit 1 if any warnings exist}
        {--tenant=          : Check health for specific tenant}';

    protected $description = 'Show mobile push notification transport health: FCM, APNs, routing, policies';

    public function handle(
        PushTransportValidator $transportValidator,
        NotificationDeliveryHealthService $deliveryHealth,
    ): int {
        $tenantId = $this->option('tenant') ?? 'platform';
        $strict   = (bool) $this->option('strict');
        $failWarn = (bool) $this->option('fail-on-warning');

        $transport = $transportValidator->validate();
        $delivery  = $deliveryHealth->getHealth($tenantId);
        $checks    = $transportValidator->getChecks();

        $score   = $delivery['score'];
        $warned  = count(array_filter($delivery['checks'], fn ($c) => $c['status'] === 'warn'));

        if ($this->option('json')) {
            $this->line(json_encode([
                'score'    => $score,
                'healthy'  => $delivery['healthy'],
                'transport' => $transport,
                'delivery'  => $delivery,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            if ($failWarn && $warned > 0) return self::FAILURE;
            return $score >= 70 ? self::SUCCESS : self::FAILURE;
        }

        $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');

        $this->newLine();
        $this->line('<fg=bright-cyan;options=bold>Mobile Push Notification Health</>');
        $this->line(str_repeat('─', 55));

        // Transport status
        $this->line('<options=bold>  Push Transport Providers</>');
        $fcmColor  = $transport['fcm']['configured'] ? 'green' : 'yellow';
        $apnsColor = $transport['apns']['configured'] ? 'green' : 'yellow';
        $fcmIcon   = $transport['fcm']['configured']  ? '<fg=green>✓</>' : '<fg=yellow>⚠</>';
        $apnsIcon  = $transport['apns']['configured'] ? '<fg=green>✓</>' : '<fg=yellow>⚠</>';
        $this->line("    {$fcmIcon}  FCM  (Android) : <fg={$fcmColor}>{$transport['fcm']['status']}</> — {$transport['fcm']['message']}");
        $this->line("    {$apnsIcon}  APNs (iOS)     : <fg={$apnsColor}>{$transport['apns']['status']}</> — {$transport['apns']['message']}");
        $this->newLine();

        // Platform authority note
        $this->line("  <fg=cyan>ℹ</> {$transport['authority_note']}");
        $this->newLine();

        // Delivery health checks
        $this->line('<options=bold>  Delivery Stack</>');
        foreach ($delivery['checks'] as $check) {
            $icon = match ($check['status']) {
                'pass'  => '<fg=green>✓</>',
                'warn'  => '<fg=yellow>⚠</>',
                'fail'  => '<fg=red>✗</>',
                default => '?',
            };
            $this->line("    {$icon}  {$check['name']}: {$check['message']}");
        }

        $this->newLine();
        $this->line(str_repeat('─', 55));
        $this->line("<fg={$color};options=bold>Push Health Score: {$score}/100</> — {$delivery['passed']}/{$delivery['total']} checks passed");
        $this->newLine();

        if ($score >= 90) {
            $this->info('Push Notifications: Production-Ready ✓');
        } elseif ($score >= 70) {
            $this->warn('Push Notifications: Partially configured — FCM/APNs credentials needed for production');
        } else {
            $this->error('Push Notifications: Not configured — push delivery disabled');
        }

        if ($failWarn && $warned > 0) return self::FAILURE;
        return $score >= 70 ? self::SUCCESS : self::FAILURE;
    }
}
