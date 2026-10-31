<?php
namespace App\Services\Infrastructure\Monitoring;

use App\Models\Infrastructure\InfraPlatformHealthLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Checks platform-level services: database, redis, queue, reverb, web,
 * SMS provider, AI provider, storage, disk, memory, CPU, failed jobs.
 */
class HealthCheckService
{
    public function checkAll(): array
    {
        return [
            'database'      => $this->checkDatabase(),
            'redis'         => $this->checkRedis(),
            'queue'         => $this->checkQueue(),
            'failed_jobs'   => $this->checkFailedJobs(),
            'reverb'        => $this->checkReverb(),
            'web'           => $this->checkWebServer(),
            'sms'           => $this->checkSmsProvider(),
            'ai'            => $this->checkAiProvider(),
            'storage'       => $this->checkStorage(),
            'disk'          => $this->checkDisk(),
            'memory'        => $this->checkMemory(),
            'cpu'           => $this->checkCpu(),
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
                return ['status' => 'warning', 'message' => "Queue backlog: {$size} jobs", 'size' => $size];
            }
            return ['status' => 'online', 'message' => "Queue size: {$size}", 'size' => $size];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => $e->getMessage()];
        }
    }

    public function checkFailedJobs(): array
    {
        try {
            $count = DB::table('failed_jobs')->count();
            $status = match (true) {
                $count >= 50  => 'critical',
                $count >= 10  => 'warning',
                default       => 'online',
            };
            return ['status' => $status, 'count' => $count, 'message' => "Failed jobs: {$count}"];
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'count' => null, 'message' => $e->getMessage()];
        }
    }

    public function checkReverb(): array
    {
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
        $primaryUrl = config('app.health_check_url', config('app.url'));
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
                    return ['status' => 'online', 'message' => "HTTP {$response->status()} in {$ms}ms", 'ms' => $ms];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return ['status' => 'warning', 'message' => 'وب‌سرور در دسترس نیست'];
    }

    public function checkSmsProvider(): array
    {
        try {
            $gateway = \App\Services\Sms\SmsGatewayResolver::resolve();
            if (! $gateway) {
                return ['status' => 'disabled', 'message' => 'SMS gateway not configured', 'name' => null];
            }

            // Check last SMS log freshness instead of live ping (avoid unwanted test sends)
            $lastLog = DB::table('sms_logs')->orderByDesc('id')->first();
            $name    = class_basename($gateway);

            if ($lastLog && \Carbon\Carbon::parse($lastLog->created_at)->isAfter(now()->subHours(6))) {
                return ['status' => 'online', 'message' => "Active gateway: {$name}", 'name' => $name];
            }

            // No recent activity — gateway is configured but untested
            return ['status' => 'warning', 'message' => "Configured ({$name}) — no recent sends", 'name' => $name];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => $e->getMessage(), 'name' => null];
        }
    }

    public function checkAiProvider(): array
    {
        $provider = config('education.ai.provider', config('services.anthropic.key') ? 'claude' : null);

        if (! $provider) {
            return ['status' => 'disabled', 'message' => 'AI provider not configured', 'name' => null];
        }

        try {
            // Check circuit-breaker state from Redis
            $cbKey   = "circuit_breaker:{$provider}";
            $cbState = Redis::connection('default')->get($cbKey);

            if ($cbState === 'open') {
                return ['status' => 'critical', 'message' => "Circuit breaker OPEN for {$provider}", 'name' => $provider];
            }

            return ['status' => 'online', 'message' => "Provider: {$provider}", 'name' => $provider];
        } catch (\Throwable $e) {
            return ['status' => 'warning', 'message' => "Cannot read CB state: {$e->getMessage()}", 'name' => $provider];
        }
    }

    public function checkStorage(): array
    {
        try {
            $providers = DB::table('infra_storage_providers')
                ->where('is_active', true)
                ->count();

            if ($providers === 0) {
                return ['status' => 'disabled', 'message' => 'No active storage providers'];
            }

            // Basic: verify local storage path is writable
            $storagePath = storage_path('app');
            if (! is_writable($storagePath)) {
                return ['status' => 'critical', 'message' => "Storage path not writable: {$storagePath}"];
            }

            return ['status' => 'online', 'message' => "{$providers} provider(s) configured"];
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'message' => $e->getMessage()];
        }
    }

    public function checkDisk(): array
    {
        try {
            $path  = base_path();
            $total = disk_total_space($path);
            $free  = disk_free_space($path);

            if ($total === false || $free === false || $total === 0) {
                return ['status' => 'unknown', 'message' => 'Cannot read disk stats'];
            }

            $usedPct = (int) round((($total - $free) / $total) * 100);
            $freeGb  = round($free / 1024 / 1024 / 1024, 2);

            $status = match (true) {
                $usedPct >= 90 => 'critical',
                $usedPct >= 75 => 'warning',
                default        => 'online',
            };

            return [
                'status'       => $status,
                'message'      => "Disk {$usedPct}% used — {$freeGb}GB free",
                'used_percent' => $usedPct,
                'free_gb'      => $freeGb,
                'total_bytes'  => (int) $total,
                'free_bytes'   => (int) $free,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'message' => $e->getMessage()];
        }
    }

    public function checkMemory(): array
    {
        try {
            // PHP process memory
            $phpUsed = memory_get_usage(true);
            $phpPeak = memory_get_peak_usage(true);

            // System memory (Linux)
            $systemPercent = null;
            $totalBytes    = null;
            $availBytes    = null;
            if (is_readable('/proc/meminfo')) {
                $lines   = file('/proc/meminfo');
                $memInfo = [];
                foreach ($lines as $line) {
                    [$key, $val] = array_pad(explode(':', $line, 2), 2, '');
                    $memInfo[trim($key)] = (int) trim(str_replace(' kB', '', $val));
                }
                $total     = $memInfo['MemTotal'] ?? 0;
                $available = $memInfo['MemAvailable'] ?? ($memInfo['MemFree'] ?? 0);
                if ($total > 0) {
                    $systemPercent = (int) round((($total - $available) / $total) * 100);
                    $totalBytes    = $total * 1024;
                    $availBytes    = $available * 1024;
                }
            }

            $status = match (true) {
                $systemPercent !== null && $systemPercent >= 90 => 'critical',
                $systemPercent !== null && $systemPercent >= 80 => 'warning',
                default                                          => 'online',
            };

            return [
                'status'         => $status,
                'message'        => $systemPercent !== null ? "System memory {$systemPercent}% used" : "PHP peak: " . round($phpPeak / 1024 / 1024, 1) . "MB",
                'used_percent'   => $systemPercent,
                'php_used_mb'    => round($phpUsed / 1024 / 1024, 1),
                'total_bytes'    => $totalBytes,
                'available_bytes' => $availBytes,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'message' => $e->getMessage()];
        }
    }

    public function checkCpu(): array
    {
        try {
            $load = sys_getloadavg();
            if ($load === false) {
                return ['status' => 'unknown', 'message' => 'Cannot read CPU load'];
            }

            $load1 = round($load[0], 2);
            $cores = (int) (trim(shell_exec('nproc 2>/dev/null') ?: '1'));
            $cores = max(1, $cores);
            $pct   = round(($load1 / $cores) * 100, 0);

            $status = match (true) {
                $pct >= 90 => 'critical',
                $pct >= 70 => 'warning',
                default    => 'online',
            };

            return [
                'status'  => $status,
                'message' => "Load avg 1m: {$load1} ({$pct}% of {$cores} cores)",
                'load_1m' => $load1,
                'load_5m'  => round($load[1], 2),
                'load_15m' => round($load[2], 2),
                'cores'    => $cores,
                'percent'  => (int) $pct,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'message' => $e->getMessage()];
        }
    }

    public function overallStatus(array $checks): string
    {
        $statuses = array_column(
            array_filter($checks, fn ($v) => is_array($v)),
            'status'
        );

        $active = array_filter($statuses, fn ($s) => $s !== 'disabled' && $s !== 'unknown');

        if (in_array('critical', $active, true)) return 'critical';
        if (in_array('warning', $active, true))  return 'warning';
        return 'online';
    }

    /**
     * Run checkAll() and persist to infra_platform_health_logs.
     */
    public function checkAndPersist(): array
    {
        $checks = $this->checkAll();
        $status = $this->overallStatus($checks);

        $disk   = $checks['disk']   ?? [];
        $mem    = $checks['memory'] ?? [];
        $cpu    = $checks['cpu']    ?? [];
        $queue  = $checks['queue']  ?? [];
        $failed = $checks['failed_jobs'] ?? [];
        $sms    = $checks['sms']    ?? [];
        $ai     = $checks['ai']     ?? [];
        $store  = $checks['storage'] ?? [];

        try {
            InfraPlatformHealthLog::create([
                'database_status'    => $checks['database']['status']  ?? 'unknown',
                'redis_status'       => $checks['redis']['status']     ?? 'unknown',
                'queue_status'       => $queue['status']               ?? 'unknown',
                'queue_size'         => $queue['size']                 ?? null,
                'failed_jobs'        => $failed['count']               ?? null,
                'reverb_status'      => $checks['reverb']['status']    ?? 'unknown',
                'sms_provider_status'=> $sms['status']                 ?? 'unknown',
                'sms_provider_name'  => $sms['name']                   ?? null,
                'ai_provider_status' => $ai['status']                  ?? 'unknown',
                'ai_provider_name'   => $ai['name']                    ?? null,
                'storage_status'     => $store['status']               ?? 'unknown',
                'disk_used_percent'  => $disk['used_percent']          ?? null,
                'disk_free_gb'       => $disk['free_gb']               ?? null,
                'memory_used_percent'=> $mem['used_percent']           ?? null,
                'cpu_load_1m'        => $cpu['load_1m']                ?? null,
                'overall_status'     => $status,
                'checked_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('HealthCheckService: failed to persist log', ['error' => $e->getMessage()]);
        }

        return $checks;
    }
}
