<?php

namespace SaaSPlatform\CoreUtils\ClientRuntime\Desktop;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use SaaSPlatform\CoreUtils\MobileRuntime\Realtime\MobileRealtimeCoordinator;
use SaaSPlatform\CoreUtils\MobileRuntime\Infrastructure\RedisRuntimeValidator;
use SaaSPlatform\CoreUtils\MobileRuntime\Infrastructure\QueueHealthInspector;

class DesktopRuntimeValidationService
{
    public function __construct(
        private readonly DesktopCapabilityRegistry  $capabilityRegistry,
        private readonly MobileRealtimeCoordinator  $realtimeCoordinator,
        private readonly RedisRuntimeValidator      $redisValidator,
        private readonly QueueHealthInspector       $queueInspector,
    ) {}

    public function validate(string $platform = 'windows'): array
    {
        $checks = [];

        $checks[] = $this->checkPlatformCompatibility($platform);
        $checks[] = $this->checkRealtimeCompatibility();
        $checks[] = $this->checkNotificationCompatibility($platform);
        $checks[] = $this->checkFileAttachmentCompatibility($platform);
        $checks[] = $this->checkPresenceCompatibility();
        $checks[] = $this->checkRedisRuntime();
        $checks[] = $this->checkQueueRuntime();
        $checks[] = $this->checkOtaCompatibility($platform);
        $checks[] = $this->checkSyncRecoveryCapability();

        return $this->buildReport($platform, $checks);
    }

    public function validateAll(): array
    {
        $results = [];
        foreach ($this->capabilityRegistry->getSupportedDesktopPlatforms() as $platform) {
            $results[$platform] = $this->validate($platform);
        }
        return $results;
    }

    private function checkPlatformCompatibility(string $platform): array
    {
        $isDesktop = $this->capabilityRegistry->isDesktopPlatform($platform);
        return [
            'check'   => 'platform_compatibility',
            'name'    => 'Desktop Platform Recognition',
            'status'  => $isDesktop ? 'pass' : 'fail',
            'message' => $isDesktop
                ? "Platform '{$platform}' recognized as desktop"
                : "Platform '{$platform}' is not a supported desktop platform",
        ];
    }

    private function checkRealtimeCompatibility(): array
    {
        $reverbConfigured = $this->realtimeCoordinator->validateTransport();
        return [
            'check'   => 'realtime_compatibility',
            'name'    => 'Realtime WebSocket (Reverb)',
            'status'  => $reverbConfigured ? 'pass' : 'warn',
            'message' => $reverbConfigured
                ? 'Reverb WebSocket configured — desktop realtime supported'
                : 'Reverb not configured — desktop realtime will use polling fallback',
        ];
    }

    private function checkNotificationCompatibility(string $platform): array
    {
        $supported = $this->capabilityRegistry->hasCapability($platform, 'local_notifications');
        return [
            'check'   => 'notification_compatibility',
            'name'    => 'Desktop Notification Compatibility',
            'status'  => $supported ? 'pass' : 'warn',
            'message' => $supported
                ? "Local OS notifications supported on {$platform}"
                : "Local notifications not supported on {$platform} — will use sync recovery fallback",
        ];
    }

    private function checkFileAttachmentCompatibility(string $platform): array
    {
        $supported = $this->capabilityRegistry->hasCapability($platform, 'file_attachments');
        $disk      = Config::get('filesystems.default', '');
        $diskOk    = ! empty($disk);

        $status = ($supported && $diskOk) ? 'pass' : ($supported ? 'warn' : 'fail');
        return [
            'check'   => 'file_attachment_compatibility',
            'name'    => 'File Attachment Support',
            'status'  => $status,
            'message' => match ($status) {
                'pass' => "File attachments supported on {$platform} (disk: {$disk})",
                'warn' => "Platform supports attachments but filesystem disk is not configured",
                'fail' => "File attachments not supported on {$platform}",
            },
        ];
    }

    private function checkPresenceCompatibility(): array
    {
        $reverbKey = Config::get('broadcasting.connections.reverb.key', '');
        $redisOk   = ! empty(Config::get('database.redis.default.host', ''));
        $ok        = ! empty($reverbKey) && $redisOk;

        return [
            'check'   => 'presence_compatibility',
            'name'    => 'Presence Runtime Compatibility',
            'status'  => $ok ? 'pass' : 'warn',
            'message' => $ok
                ? 'Presence runtime (Reverb + Redis) available for desktop clients'
                : 'Presence requires Reverb key and Redis — one or both are missing',
        ];
    }

    private function checkRedisRuntime(): array
    {
        try {
            $result  = $this->redisValidator->validate();
            $ok      = ($result['status'] ?? '') === \SaaSPlatform\CoreUtils\MobileRuntime\Infrastructure\RedisRuntimeValidator::STATUS_CONNECTED;
            return [
                'check'   => 'redis_runtime',
                'name'    => 'Redis Runtime',
                'status'  => $ok ? 'pass' : 'warn',
                'message' => $result['message'] ?? ($ok ? 'Redis connected' : 'Redis unavailable'),
            ];
        } catch (\Throwable $e) {
            return [
                'check'   => 'redis_runtime',
                'name'    => 'Redis Runtime',
                'status'  => 'warn',
                'message' => 'Redis validation error: ' . $e->getMessage(),
            ];
        }
    }

    private function checkQueueRuntime(): array
    {
        try {
            $result = $this->queueInspector->validate();
            $ok     = ($result['production_ready'] ?? false);
            $accept = ($result['acceptable'] ?? false);
            return [
                'check'   => 'queue_runtime',
                'name'    => 'Queue Runtime',
                'status'  => $ok ? 'pass' : ($accept ? 'warn' : 'warn'),
                'message' => $result['message'] ?? ($ok ? 'Queue ready' : 'Queue not production-ready'),
            ];
        } catch (\Throwable $e) {
            return [
                'check'   => 'queue_runtime',
                'name'    => 'Queue Runtime',
                'status'  => 'warn',
                'message' => 'Queue inspection error: ' . $e->getMessage(),
            ];
        }
    }

    private function checkOtaCompatibility(string $platform): array
    {
        $supported = $this->capabilityRegistry->hasCapability($platform, 'ota');
        return [
            'check'   => 'ota_compatibility',
            'name'    => 'OTA Update Compatibility',
            'status'  => $supported ? 'pass' : 'warn',
            'message' => $supported
                ? "OTA updates compatible with {$platform}"
                : "OTA not supported on {$platform}",
        ];
    }

    private function checkSyncRecoveryCapability(): array
    {
        $supported = $this->capabilityRegistry->hasCapability('windows', 'sync_recovery');
        return [
            'check'   => 'sync_recovery',
            'name'    => 'Offline Sync Recovery',
            'status'  => $supported ? 'pass' : 'warn',
            'message' => $supported
                ? 'Offline sync recovery available — missed notifications will be queued'
                : 'Sync recovery not configured',
        ];
    }

    private function buildReport(string $platform, array $checks): array
    {
        $passed  = count(array_filter($checks, fn ($c) => $c['status'] === 'pass'));
        $warned  = count(array_filter($checks, fn ($c) => $c['status'] === 'warn'));
        $failed  = count(array_filter($checks, fn ($c) => $c['status'] === 'fail'));
        $total   = count($checks);
        $score   = $total > 0 ? (int) round($passed / $total * 100) : 0;

        return [
            'platform'        => $platform,
            'checks'          => $checks,
            'passed'          => $passed,
            'warned'          => $warned,
            'failed'          => $failed,
            'total'           => $total,
            'score'           => $score,
            'grade'           => $this->getGrade($score),
            'desktop_ready'   => $failed === 0 && $score >= 70,
            'capabilities'    => $this->capabilityRegistry->getCapabilities($platform),
        ];
    }

    private function getGrade(int $score): string
    {
        return match (true) {
            $score >= 95 => 'A',
            $score >= 85 => 'B',
            $score >= 70 => 'C',
            $score >= 55 => 'D',
            default      => 'F',
        };
    }
}
