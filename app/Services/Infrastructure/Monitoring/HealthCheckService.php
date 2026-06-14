<?php
namespace App\Services\Infrastructure\Monitoring;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;

/**
 * Checks platform-level services: database, redis, queue workers, reverb.
 */
class HealthCheckService
{
    public function checkAll(): array
    {
        return [
            'database'      => $this->checkDatabase(),
            'redis'         => $this->checkRedis(),
            'queue'         => $this->checkQueue(),
            'reverb'        => $this->checkReverb(),
            'web'           => $this->checkWebServer(),
            'checked_at'    => now()->toIso8601String(),
        ];
    }

    public function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');
            return ['status' => 'online', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => $e->getMessage()];
        }
    }

    public function checkRedis(): array
    {
        try {
            $pong = Redis::connection('default')->ping();
            if ($pong) {
                return ['status' => 'online', 'message' => 'PONG'];
            }
            return ['status' => 'warning', 'message' => 'No PONG response'];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => $e->getMessage()];
        }
    }

    public function checkQueue(): array
    {
        try {
            $size = Queue::size();
            if ($size > 1000) {
                return ['status' => 'warning', 'message' => "Queue backlog: {$size} jobs"];
            }
            return ['status' => 'online', 'message' => "Queue size: {$size}"];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => $e->getMessage()];
        }
    }

    public function checkReverb(): array
    {
        // If Reverb is not the configured broadcast driver, skip
        if (config('broadcasting.default') !== 'reverb') {
            return ['status' => 'disabled', 'message' => 'WebSocket غیرفعال'];
        }

        $host = config('reverb.servers.reverb.host', '0.0.0.0');
        $port = config('reverb.servers.reverb.port', 8080);

        if ($host === '0.0.0.0') {
            $host = '127.0.0.1';
        }

        $connection = @fsockopen($host, (int) $port, $errno, $errstr, 2);
        if ($connection) {
            fclose($connection);
            return ['status' => 'online', 'message' => "Reverb listening on {$host}:{$port}"];
        }
        return ['status' => 'warning', 'message' => "Reverb not reachable on {$host}:{$port}"];
    }

    public function checkWebServer(): array
    {
        // Support explicit override via env, then fall back to APP_URL
        $primaryUrl = config('app.health_check_url', config('app.url'));

        // For dev-only hostnames (.test / .local), also try localhost
        $parsedHost = parse_url($primaryUrl, PHP_URL_HOST) ?? '';
        $isDevUrl   = str_ends_with($parsedHost, '.test')
                   || str_ends_with($parsedHost, '.local')
                   || $parsedHost === 'localhost';

        $candidates = array_filter(array_unique(array_merge(
            [$primaryUrl],
            $isDevUrl ? ['http://127.0.0.1'] : []
        )));

        foreach ($candidates as $url) {
            try {
                $start    = microtime(true);
                $response = Http::timeout(5)->get($url);
                $ms       = (int) ((microtime(true) - $start) * 1000);

                if ($response->status() < 500) {
                    return ['status' => 'online', 'message' => "HTTP {$response->status()} in {$ms}ms"];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return ['status' => 'warning', 'message' => 'وب‌سرور در دسترس نیست'];
    }

    public function overallStatus(array $checks): string
    {
        $statuses = array_column(
            array_filter($checks, fn ($v) => is_array($v)),
            'status'
        );

        // Disabled components do not affect overall status
        $active = array_filter($statuses, fn ($s) => $s !== 'disabled');

        if (in_array('critical', $active, true)) {
            return 'critical';
        }
        if (in_array('warning', $active, true)) {
            return 'warning';
        }
        return 'online';
    }
}
