<?php
namespace App\Services\Sms;

use App\Contracts\SmsGatewayInterface;
use App\Models\SmsLog;
use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Wraps two gateways: tries primary first; falls back to secondary on failure.
 * Tracks provider health in cache AND persists each attempt to sms_logs table.
 */
class FailoverSmsGateway implements SmsGatewayInterface
{
    public function __construct(
        private readonly SmsGatewayInterface $primary,
        private readonly SmsGatewayInterface $secondary
    ) {}

    public function send(string $to, string $message): bool
    {
        $t = microtime(true);
        $primaryOk = $this->trySend($this->primary, $to, $message, 'primary', $t);

        if ($primaryOk) {
            return true;
        }

        Log::channel('single')->warning('SMS primary failed, trying failover', ['to' => $to]);

        $t2 = microtime(true);
        $result = $this->trySend($this->secondary, $to, $message, 'secondary', $t2);

        if (!$result) {
            Log::channel('single')->error('SMS failover also failed', ['to' => $to]);
            try {
                app(SlackAlertService::class)->critical(
                    'SMS Delivery Failed — Both Providers',
                    "Both primary and secondary SMS providers failed. Messages are NOT being delivered.",
                    ['recipient_masked' => '***' . substr($to, -4)]
                );
            } catch (\Throwable) {}
        }

        return $result;
    }

    private function trySend(
        SmsGatewayInterface $gateway,
        string $to,
        string $message,
        string $slot,
        float $startedAt
    ): bool {
        $error  = null;
        $result = false;

        try {
            $result = $gateway->send($to, $message);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $ms = (int) round((microtime(true) - $startedAt) * 1000);

        $this->recordHealth($slot, $result);

        try {
            SmsLog::create([
                'provider'         => $slot,
                'recipient'        => substr($to, 0, 20),
                'message'          => $message,
                'success'          => $result,
                'error_message'    => $error,
                'response_time_ms' => $ms,
            ]);
        } catch (\Throwable) {
            // Never let logging failure break the SMS send path
        }

        return $result;
    }

    private function recordHealth(string $slot, bool $success): void
    {
        $key   = "sms_health_{$slot}";
        $stats = Cache::get($key, ['success' => 0, 'failure' => 0, 'last_checked_at' => null]);
        $success ? $stats['success']++ : $stats['failure']++;
        $stats['last_checked_at'] = now()->toIso8601String();
        Cache::put($key, $stats, now()->addDay());
    }
}
