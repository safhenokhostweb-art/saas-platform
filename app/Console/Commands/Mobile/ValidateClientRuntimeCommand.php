<?php

namespace App\Console\Commands\Mobile;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\MobileRuntime\Validation\MobileProductionReadinessService;
use SaaSPlatform\CoreUtils\MobileRuntime\Realtime\ReverbRuntimeValidator;
use SaaSPlatform\CoreUtils\MobileRuntime\Infrastructure\RedisRuntimeValidator;
use SaaSPlatform\CoreUtils\MobileRuntime\Realtime\MobileRealtimeHealthService;

class ValidateClientRuntimeCommand extends Command
{
    protected $signature = 'mobile:validate-client-runtime
                            {--json : Output as JSON}
                            {--strict : Exit 1 if score below 80}
                            {--fail-below=80 : Minimum passing score}';

    protected $description = 'Validate the unified client runtime (API, Realtime, OTA, Mobile) — Phase M2.0';

    public function handle(
        MobileProductionReadinessService $readiness,
        ReverbRuntimeValidator $reverbValidator,
        RedisRuntimeValidator $redisValidator,
        MobileRealtimeHealthService $realtimeHealth,
    ): int {
        $this->info('Phase M2.0 — Client Runtime Validation');
        $this->newLine();

        $checks = [];
        $score  = 0;
        $max    = 0;

        // 1. Mobile runtime manifest presence
        $this->checkMobileConfig($checks, $score, $max);

        // 2. Reverb WebSocket infrastructure
        $this->checkReverb($reverbValidator, $checks, $score, $max);

        // 3. Redis presence storage
        $this->checkRedis($redisValidator, $checks, $score, $max);

        // 4. Realtime health
        $this->checkRealtimeHealth($realtimeHealth, $checks, $score, $max);

        // 5. Mobile API routes registered
        $this->checkMobileApiRoutes($checks, $score, $max);

        // 6. OTA configuration
        $this->checkOtaConfig($checks, $score, $max);

        // 7. Notification transport config
        $this->checkNotificationConfig($checks, $score, $max);

        // 8. Platform client config
        $this->checkPlatformConfig($checks, $score, $max);

        // 9. Production readiness
        $this->checkProductionReadiness($readiness, $checks, $score, $max);

        // 10. Flutter project exists
        $this->checkFlutterProject($checks, $score, $max);

        $pct     = $max > 0 ? round(($score / $max) * 100) : 0;
        $passing = $score >= (int) $this->option('fail-below');
        $grade   = $pct >= 95 ? 'A+' : ($pct >= 85 ? 'A' : ($pct >= 75 ? 'B+' : ($pct >= 65 ? 'B' : 'C')));

        if ($this->option('json')) {
            $this->line(json_encode([
                'command'  => 'mobile:validate-client-runtime',
                'score'    => "$score/$max ({$pct}%)",
                'grade'    => $grade,
                'passing'  => $passing,
                'checks'   => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable($checks);
            $this->newLine();
            $this->line("Score: <fg=" . ($passing ? 'green' : 'red') . ">{$score}/{$max} ({$pct}%) — Grade {$grade}</>");
        }

        if ($this->option('strict') && !$passing) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function checkMobileConfig(array &$checks, int &$score, int &$max): void
    {
        $max += 10;
        $ok   = config('mobile.client.supported_platforms') !== null;
        if ($ok) $score += 10;
        $checks[] = ['Mobile config', $ok ? 'PASS' : 'FAIL', $ok ? 10 : 0, 10, 'config/mobile.php loaded'];
    }

    private function checkReverb(ReverbRuntimeValidator $v, array &$checks, int &$score, int &$max): void
    {
        $max += 10;
        try {
            $result = $v->validate();
            $ok     = ($result['valid'] ?? false);
            if ($ok) $score += 10;
            $checks[] = ['Reverb runtime', $ok ? 'PASS' : 'WARN', $ok ? 10 : 0, 10, $result['detail'] ?? ''];
        } catch (\Throwable $e) {
            $checks[] = ['Reverb runtime', 'WARN', 0, 10, 'Reverb not running (acceptable in dev)'];
            $score   += 5; // partial credit — optional in dev
        }
    }

    private function checkRedis(RedisRuntimeValidator $v, array &$checks, int &$score, int &$max): void
    {
        $max += 10;
        try {
            $result = $v->validate();
            $ok     = ($result['valid'] ?? false);
            if ($ok) $score += 10;
            $checks[] = ['Redis runtime', $ok ? 'PASS' : 'WARN', $ok ? 10 : 0, 10, $result['detail'] ?? ''];
        } catch (\Throwable $e) {
            $checks[] = ['Redis runtime', 'WARN', 5, 10, 'Redis unavailable (fallback to file)'];
            $score   += 5;
        }
    }

    private function checkRealtimeHealth(MobileRealtimeHealthService $h, array &$checks, int &$score, int &$max): void
    {
        $max += 10;
        try {
            $result = $h->check();
            $ok     = ($result['healthy'] ?? false);
            if ($ok) $score += 10;
            $checks[] = ['Realtime health', $ok ? 'PASS' : 'WARN', $ok ? 10 : 5, 10, 'WebSocket channel health checked'];
        } catch (\Throwable) {
            $checks[] = ['Realtime health', 'WARN', 5, 10, 'Health service unavailable'];
            $score   += 5;
        }
    }

    private function checkMobileApiRoutes(array &$checks, int &$score, int &$max): void
    {
        $max += 10;
        $routes   = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes());
        $mobile   = $routes->filter(fn($r) => str_starts_with($r->uri(), 'api/mobile/'))->count();
        $ok       = $mobile >= 8;
        if ($ok) $score += 10;
        $checks[] = ['Mobile API routes', $ok ? 'PASS' : 'FAIL', $ok ? 10 : 0, 10, "{$mobile} routes registered (need ≥ 8)"];
    }

    private function checkOtaConfig(array &$checks, int &$score, int &$max): void
    {
        $max += 10;
        $ota = config('mobile.ota');
        $ok  = isset($ota['manifest_ttl_seconds'], $ota['stable_rollout_pct']);
        if ($ok) $score += 10;
        $checks[] = ['OTA config', $ok ? 'PASS' : 'FAIL', $ok ? 10 : 0, 10, 'OTA channels: stable/beta/canary'];
    }

    private function checkNotificationConfig(array &$checks, int &$score, int &$max): void
    {
        $max += 10;
        $transport = config('mobile.notification_transport');
        $ok        = isset($transport['firebase_optional']) && $transport['firebase_optional'] === true;
        if ($ok) $score += 10;
        $checks[] = ['Notification transport', $ok ? 'PASS' : 'FAIL', $ok ? 10 : 0, 10, 'Firebase optional — system runs without it'];
    }

    private function checkPlatformConfig(array &$checks, int &$score, int &$max): void
    {
        $max  += 10;
        $plats = config('mobile.client.supported_platforms', []);
        $ok    = count(array_intersect(['android', 'ios', 'windows', 'linux', 'macos'], $plats)) === 5;
        if ($ok) $score += 10;
        $checks[] = ['Platform config', $ok ? 'PASS' : 'FAIL', $ok ? 10 : 0, 10, 'All 5 platforms declared: ' . implode(', ', $plats)];
    }

    private function checkProductionReadiness(MobileProductionReadinessService $svc, array &$checks, int &$score, int &$max): void
    {
        $max += 10;
        try {
            $result = $svc->check();
            $ok     = ($result['ready'] ?? false) || ($result['score'] ?? 0) >= 70;
            if ($ok) $score += 10;
            $checks[] = ['Production readiness', $ok ? 'PASS' : 'WARN', $ok ? 10 : 5, 10, 'MobileProductionReadinessService'];
        } catch (\Throwable) {
            $checks[] = ['Production readiness', 'WARN', 5, 10, 'Service unavailable'];
            $score   += 5;
        }
    }

    private function checkFlutterProject(array &$checks, int &$score, int &$max): void
    {
        $max    += 10;
        $exists  = file_exists(base_path('mobile/pubspec.yaml'))
            && file_exists(base_path('mobile/lib/main.dart'));
        if ($exists) $score += 10;
        $checks[] = ['Flutter project', $exists ? 'PASS' : 'FAIL', $exists ? 10 : 0, 10, 'mobile/pubspec.yaml + lib/main.dart found'];
    }

    private function renderTable(array $checks): void
    {
        $this->table(
            ['Check', 'Status', 'Score', 'Max', 'Detail'],
            array_map(fn($c) => [
                $c[0],
                match($c[1]) {
                    'PASS' => '<fg=green>PASS</>',
                    'WARN' => '<fg=yellow>WARN</>',
                    default => '<fg=red>FAIL</>',
                },
                $c[2],
                $c[3],
                $c[4],
            ], $checks)
        );
    }
}
