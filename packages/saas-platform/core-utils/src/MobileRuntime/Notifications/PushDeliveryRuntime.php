<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\MobilePushTokenRegistry;

class PushDeliveryRuntime
{
    private const PREFIX = 'mobile:delivery:';
    private const TTL    = 3600;

    public function __construct(
        private readonly MobilePushTokenRegistry $tokenRegistry,
    ) {}

    public function deliver(string $deviceId, array $notification): bool
    {
        $tokenData = $this->tokenRegistry->getToken($deviceId);
        if (! $tokenData) {
            Log::debug('mobile.push.no_token', ['device_id' => $deviceId]);
            return false;
        }

        $platform  = $tokenData['platform'] ?? 'fcm';
        $token     = $tokenData['token'] ?? '';

        $success = match ($platform) {
            'fcm'  => $this->deliverFcm($token, $notification),
            'apns' => $this->deliverApns($token, $notification),
            default => false,
        };

        $this->recordDeliveryAttempt($deviceId, $notification['id'] ?? Str::uuid(), $success);
        return $success;
    }

    public function deliverFcm(string $token, array $notification): bool
    {
        $serverKey = Config::get('mobile.fcm_server_key', '');
        if (empty($serverKey)) {
            Log::debug('mobile.push.fcm_key_missing');
            return false;
        }

        $projectId = Config::get('mobile.fcm_project_id', '');
        $payload   = $this->buildFcmPayload($notification);
        $payload['token'] = $token;

        try {
            // FCM v1 HTTP API — transport only, platform not involved in business logic
            $response = Http::withToken($serverKey)
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", ['message' => $payload]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('mobile.push.fcm_failed', ['status' => $response->status(), 'body' => $response->body()]);

            if ($response->status() === 404) {
                // Token expired — caller should mark it invalid
            }
            return false;
        } catch (\Throwable $e) {
            Log::error('mobile.push.fcm_exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function deliverApns(string $token, array $notification): bool
    {
        // APNs delivery — future implementation
        Log::debug('mobile.push.apns_stub', ['token_prefix' => substr($token, 0, 8)]);
        return false;
    }

    public function getDeliveryProvider(string $platform): string
    {
        return match (strtolower($platform)) {
            'ios'     => 'apns',
            'android' => 'fcm',
            default   => 'fcm',
        };
    }

    public function buildFcmPayload(array $notification): array
    {
        return [
            'notification' => [
                'title' => $notification['title'] ?? '',
                'body'  => $notification['body'] ?? '',
            ],
            'data'         => array_merge(
                $notification['data'] ?? [],
                ['type' => $notification['type'] ?? 'general', 'priority' => $notification['priority'] ?? 'normal']
            ),
            'android'      => ['priority' => ($notification['priority'] ?? 'normal') === 'high' ? 'high' : 'normal'],
        ];
    }

    public function buildApnsPayload(array $notification): array
    {
        return [
            'aps' => [
                'alert' => ['title' => $notification['title'] ?? '', 'body' => $notification['body'] ?? ''],
                'badge' => 1,
                'sound' => ($notification['priority'] ?? 'normal') === 'critical' ? 'critical.aiff' : 'default',
            ],
            'data' => $notification['data'] ?? [],
        ];
    }

    public function recordDeliveryAttempt(string $deviceId, string $notificationId, bool $success, ?string $error = null): void
    {
        $key    = self::PREFIX . "stats:{$deviceId}";
        $stats  = Cache::get($key, ['sent' => 0, 'delivered' => 0, 'failed' => 0]);
        $stats['sent']++;
        $success ? $stats['delivered']++ : $stats['failed']++;
        Cache::put($key, $stats, self::TTL);
    }

    public function getDeliveryStats(string $tenantId, int $hours = 24): array
    {
        return Cache::get(self::PREFIX . "tenant_stats:{$tenantId}", ['sent' => 0, 'delivered' => 0, 'failed' => 0]);
    }
}
