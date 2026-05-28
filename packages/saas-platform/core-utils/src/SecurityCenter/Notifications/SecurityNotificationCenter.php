<?php

namespace SaaSPlatform\CoreUtils\SecurityCenter\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SecurityNotificationCenter
{
    private const NOTIFICATION_KEY  = 'security:notifications:';
    private const ALERT_KEY         = 'security:alerts:active';
    private const MAX_NOTIFICATIONS = 200;

    public function notifyCriticalVulnerability(string $title, string $description, array $context = []): string
    {
        $id = $this->dispatch([
            'type'        => 'critical_vulnerability',
            'title'       => $title,
            'description' => $description,
            'severity'    => 'critical',
            'bypass_quiet'=> true,
            'persistent'  => true,
            'requires_ack'=> true,
        ] + $context);

        $this->escalateToAllChannels($id, $title, $description, 'critical');

        return $id;
    }

    public function notifySecurityPatch(string $patchId, string $patchTitle, string $severity): string
    {
        return $this->dispatch([
            'type'      => 'security_patch_available',
            'title'     => "Security Patch Available: {$patchTitle}",
            'patch_id'  => $patchId,
            'severity'  => $severity,
            'bypass_quiet' => $severity === 'critical',
            'persistent'   => $severity === 'critical',
            'requires_ack' => false,
        ]);
    }

    public function notifyPatchInstalled(string $patchId, string $tenantId): string
    {
        return $this->dispatch([
            'type'      => 'patch_installed',
            'title'     => "Security patch installed successfully",
            'patch_id'  => $patchId,
            'tenant_id' => $tenantId,
            'severity'  => 'info',
            'bypass_quiet' => false,
            'persistent'   => false,
        ]);
    }

    public function notifyPatchRollback(string $patchId, string $tenantId, string $reason): string
    {
        return $this->dispatch([
            'type'      => 'patch_rolled_back',
            'title'     => "Security patch rolled back",
            'patch_id'  => $patchId,
            'tenant_id' => $tenantId,
            'reason'    => $reason,
            'severity'  => 'warning',
            'bypass_quiet' => true,
            'persistent'   => true,
        ]);
    }

    public function notifyIntegrityViolation(string $component, string $detail): string
    {
        return $this->dispatch([
            'type'        => 'integrity_violation',
            'title'       => "Integrity Violation Detected: {$component}",
            'description' => $detail,
            'severity'    => 'critical',
            'bypass_quiet'=> true,
            'persistent'  => true,
            'requires_ack'=> true,
        ]);
    }

    public function getActiveAlerts(string $tenantId = 'global'): array
    {
        $all    = Cache::get(self::ALERT_KEY, []);
        $active = array_filter($all, fn ($a) =>
            (! isset($a['tenant_id']) || $a['tenant_id'] === $tenantId || $a['tenant_id'] === 'global')
            && ! ($a['acknowledged'] ?? false)
        );
        return array_values($active);
    }

    public function getNotifications(string $tenantId = 'global', int $limit = 20): array
    {
        $all = Cache::get(self::NOTIFICATION_KEY . $tenantId, []);
        return array_slice(array_reverse($all), 0, $limit);
    }

    public function acknowledgeAlert(string $alertId, string $tenantId): void
    {
        $alerts = Cache::get(self::ALERT_KEY, []);
        if (isset($alerts[$alertId])) {
            $alerts[$alertId]['acknowledged']    = true;
            $alerts[$alertId]['acknowledged_at'] = now()->toIso8601String();
            $alerts[$alertId]['acknowledged_by'] = $tenantId;
            Cache::put(self::ALERT_KEY, $alerts, 86400 * 90);
        }
    }

    public function dispatch(array $notification): string
    {
        $id           = uniqid('secnotif_', true);
        $tenantId     = $notification['tenant_id'] ?? 'global';

        $full = array_merge($notification, [
            'id'          => $id,
            'tenant_id'   => $tenantId,
            'dispatched_at' => now()->toIso8601String(),
            'read'        => false,
        ]);

        // Store in per-tenant notification list
        $notifications   = Cache::get(self::NOTIFICATION_KEY . $tenantId, []);
        $notifications[] = $full;
        if (count($notifications) > self::MAX_NOTIFICATIONS) {
            $notifications = array_slice($notifications, -self::MAX_NOTIFICATIONS);
        }
        Cache::put(self::NOTIFICATION_KEY . $tenantId, $notifications, 86400 * 90);

        // Store as active alert if persistent
        if ($notification['persistent'] ?? false) {
            $alerts       = Cache::get(self::ALERT_KEY, []);
            $alerts[$id]  = $full;
            Cache::put(self::ALERT_KEY, $alerts, 86400 * 90);
        }

        Log::channel('security')->info("SecurityCenter notification: {$full['title']}", [
            'id'       => $id,
            'severity' => $full['severity'] ?? 'info',
            'tenant'   => $tenantId,
        ]);

        return $id;
    }

    private function escalateToAllChannels(string $id, string $title, string $description, string $severity): void
    {
        // Dashboard notification already dispatched above
        // Email: queue a SecurityCriticalAlertMail (if mail configured)
        // SMS: via configured SMS gateway
        // In-app: already in active alerts
        Log::critical("SecurityCenter CRITICAL ESCALATION: {$title}", [
            'id'          => $id,
            'description' => $description,
            'severity'    => $severity,
        ]);
    }
}
