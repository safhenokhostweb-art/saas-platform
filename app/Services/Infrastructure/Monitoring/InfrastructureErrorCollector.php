<?php
namespace App\Services\Infrastructure\Monitoring;

use App\Models\Infrastructure\InfraIncident;
use App\Services\ExpirySmsService;
use App\Services\Infrastructure\SlackAlertService;
use App\Services\Notification\NotificationCenterService;
use Illuminate\Support\Facades\Log;

/**
 * Central collector for infrastructure errors.
 * Creates or updates infra_incidents, fires IncidentLifecycleService::open(),
 * throttles SMS, fires Slack alerts, and mirrors to NotificationCenter.
 */
class InfrastructureErrorCollector
{
    private SlackAlertService $slack;

    public function __construct(SlackAlertService $slack)
    {
        $this->slack = $slack;
    }

    /**
     * @param string $severity  critical | high | medium | low
     */
    public function collect(
        string  $type,
        string  $title,
        string  $message,
        ?int    $siteId   = null,
        string  $severity = 'medium'
    ): InfraIncident {
        $incident = InfraIncident::where('site_id', $siteId)
            ->where('type', $type)
            ->active()
            ->latest()
            ->first();

        if ($incident) {
            $incident->update(['message' => $message]);
        } else {
            $incident = InfraIncident::create([
                'site_id'  => $siteId,
                'severity' => $severity,
                'type'     => $type,
                'title'    => $title,
                'message'  => $message,
                'status'   => InfraIncident::STATUS_OPEN,
            ]);

            // Wire lifecycle: add system note + support ticket for critical/high
            try {
                app(IncidentLifecycleService::class)->open($incident);
            } catch (\Throwable $e) {
                Log::warning('InfrastructureErrorCollector: lifecycle open failed', ['error' => $e->getMessage()]);
            }
        }

        $this->maybeNotify($incident);

        return $incident;
    }

    public function resolveForSite(int $siteId, string $type): void
    {
        InfraIncident::where('site_id', $siteId)
            ->where('type', $type)
            ->active()
            ->update([
                'status'      => InfraIncident::STATUS_RESOLVED,
                'resolved_at' => now(),
            ]);
    }

    public function collectQueueFailure(string $jobName, string $error): void
    {
        $this->collect(
            type:     'queue_dead',
            title:    "Queue Job Failed: {$jobName}",
            message:  $error,
            siteId:   null,
            severity: 'critical'
        );
    }

    public function collectSchedulerFailure(string $command, string $error): void
    {
        $this->collect(
            type:     'scheduler_failure',
            title:    "Scheduled Task Failed: {$command}",
            message:  $error,
            siteId:   null,
            severity: 'medium'
        );
    }

    private function maybeNotify(InfraIncident $incident): void
    {
        if (! $incident->needsSmsAlert()) {
            return;
        }

        $severityLabel = match ($incident->severity) {
            'critical' => 'بحرانی',
            'high'     => 'بالا',
            'medium'   => 'متوسط',
            default    => 'پایین',
        };
        $smsText = "[{$severityLabel}] {$incident->title}. {$incident->message}";

        $this->trySendSms($smsText);

        if (in_array($incident->severity, ['critical', 'high'], true)) {
            $this->slack->critical($incident->title, $incident->message ?? '', [
                'Type'     => $incident->type,
                'Site ID'  => $incident->site_id ?? 'platform',
                'Severity' => $incident->severity,
            ]);
        } else {
            $this->slack->warning($incident->title, $incident->message ?? '', [
                'Type'     => $incident->type,
                'Site ID'  => $incident->site_id ?? 'platform',
            ]);
        }

        $incident->markSmsSent();

        // Mirror to Notification Center
        try {
            $priority = in_array($incident->severity, ['critical', 'high'], true) ? $incident->severity : 'medium';
            app(NotificationCenterService::class)->infrastructure(
                $incident->title,
                $incident->message ?? '',
                $priority,
                $incident->site_id
            );
        } catch (\Throwable $e) {
            Log::warning('InfrastructureErrorCollector: NC notify failed', ['error' => $e->getMessage()]);
        }
    }

    private function trySendSms(string $text): void
    {
        try {
            $gateway = \App\Services\Sms\SmsGatewayResolver::resolve();
            if (! $gateway) {
                return;
            }

            $adminMobiles = \App\Models\User::where('is_active', true)
                ->where('is_admin', true)
                ->whereNotNull('mobile')
                ->where('mobile', '!=', '')
                ->pluck('mobile')
                ->unique()
                ->values()
                ->all();

            foreach ($adminMobiles as $mobile) {
                $mobile = $this->normalizeMobile((string) $mobile);
                if ($mobile) {
                    $gateway->send($mobile, $text);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('InfrastructureErrorCollector: SMS send failed', ['error' => $e->getMessage()]);
        }
    }

    private function normalizeMobile(string $m): string
    {
        $m = preg_replace('/\D/', '', $m);
        if (str_starts_with($m, '98')) return $m;
        if (str_starts_with($m, '0'))  return '98' . substr($m, 1);
        if (strlen($m) === 10)         return '98' . $m;
        return $m;
    }
}
