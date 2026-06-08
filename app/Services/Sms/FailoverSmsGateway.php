<?php
namespace App\Services\Sms;

use App\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Wraps two gateways: tries primary first; falls back to secondary on failure.
 * Tracks provider health in cache so the dashboard can report it.
 */
class FailoverSmsGateway implements SmsGatewayInterface
{
    public function __construct(
        private readonly SmsGatewayInterface $primary,
        private readonly SmsGatewayInterface $secondary
    ) {}

    public function send(string $to, string $message): bool
    {
        if ($this->primary->send($to, $message)) {
            $this->recordHealth('primary', true);
            return true;
        }

        $this->recordHealth('primary', false);
        Log::channel('single')->warning('SMS primary failed, trying failover', ['to' => $to]);

        $result = $this->secondary->send($to, $message);
        $this->recordHealth('secondary', $result);

        if (!$result) {
            Log::channel('single')->error('SMS failover also failed', ['to' => $to]);
        }

        return $result;
    }

    private function recordHealth(string $slot, bool $success): void
    {
        $key = "sms_health_{$slot}";
        $stats = Cache::get($key, ['success' => 0, 'failure' => 0, 'last_checked_at' => null]);
        $success ? $stats['success']++ : $stats['failure']++;
        $stats['last_checked_at'] = now()->toIso8601String();
        Cache::put($key, $stats, now()->addDay());
    }
}
