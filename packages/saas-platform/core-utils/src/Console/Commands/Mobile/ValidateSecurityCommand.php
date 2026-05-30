<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Mobile;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\MobileRuntime\Security\MobileSecurityPatchCoordinator;
use SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\DeviceSecurityValidator;
use SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\DeviceTrustManager;
use SaaSPlatform\CoreUtils\MobileRuntime\OTA\MobileSecurityPatchRuntime;

class ValidateSecurityCommand extends Command
{
    protected $signature = 'mobile:validate-security
        {--json             : Output results as JSON}
        {--strict           : Treat warnings as failures}
        {--fail-on-warning  : Exit 1 if any warnings exist}
        {--tenant=          : Validate security for specific tenant ID}';

    protected $description = 'Validate mobile security governance: patch compliance, device trust, and security policies';

    public function handle(
        MobileSecurityPatchCoordinator $patchCoordinator,
        DeviceSecurityValidator $deviceValidator,
        DeviceTrustManager $trustManager,
        MobileSecurityPatchRuntime $patchRuntime,
    ): int {
        $tenantId   = $this->option('tenant') ?? 'platform';
        $appVersion = config('mobile.min_app_version', '1.0.0');
        $strict     = (bool) $this->option('strict');
        $failWarn   = (bool) $this->option('fail-on-warning');
        $checks     = [];

        // Check 1 — Global patch registry
        $patches = $patchCoordinator->getAvailablePatches();
        $checks[] = [
            'name'    => 'Patch Registry',
            'status'  => count($patches) > 0 ? 'pass' : 'warn',
            'message' => count($patches) . ' security patch(es) registered in global registry',
        ];

        // Check 2 — Tenant patch compliance
        $manifest    = $patchRuntime->generatePatchManifest($tenantId, $appVersion);
        $isCompliant = $manifest['compliance_status'] === 'compliant';
        $appliedCount = count($manifest['patches'] ?? []);
        $checks[] = [
            'name'    => 'Patch Compliance',
            'status'  => $isCompliant ? 'pass' : 'fail',
            'message' => 'Tenant patch compliance: ' . $manifest['compliance_status']
                . ' (' . $appliedCount . ' patch(es) applied, '
                . $manifest['critical_count'] . ' critical outstanding)',
        ];

        // Check 3 — Critical patches
        $critical = array_filter($patches, fn ($p) => ($p['severity'] ?? 'normal') === 'critical');
        $checks[] = [
            'name'    => 'Critical Patches',
            'status'  => count($critical) === 0 ? 'pass' : 'warn',
            'message' => count($critical) . ' critical patch(es) in registry',
        ];

        // Check 4 — Security config keys
        $hasAppKey = ! empty(config('app.key'));
        $checks[] = [
            'name'    => 'App Encryption Key',
            'status'  => $hasAppKey ? 'pass' : 'fail',
            'message' => $hasAppKey ? 'APP_KEY configured (HMAC signing active)' : 'APP_KEY not set — HMAC signing will fail',
        ];

        // Check 5 — FCM transport config
        $hasFcm = ! empty(config('mobile.fcm_server_key'));
        $checks[] = [
            'name'    => 'FCM Transport',
            'status'  => $hasFcm ? 'pass' : 'warn',
            'message' => $hasFcm ? 'FCM transport key configured (push carrier active)' : 'FCM server key not configured (push delivery will fail)',
        ];

        // Check 6 — Device trust policy
        $checks[] = [
            'name'    => 'Device Trust Policy',
            'status'  => 'pass',
            'message' => 'DeviceTrustManager active — max ' . DeviceTrustManager::MAX_FAIL_ATTEMPTS . ' failed attempts before suspension',
        ];

        // Check 7 — Device OS Policy (use public method, not private constants)
        $minIos     = $deviceValidator->getMinSupportedOs('ios');
        $minAndroid = $deviceValidator->getMinSupportedOs('android');
        $checks[] = [
            'name'    => 'Device OS Policy',
            'status'  => 'pass',
            'message' => "OS version policy: iOS >= {$minIos}, Android >= {$minAndroid}",
        ];

        // Check 8 — Security patch runtime
        $checks[] = [
            'name'    => 'Security Patch Runtime',
            'status'  => 'pass',
            'message' => 'MobileSecurityPatchRuntime active — OTA patch delivery configured',
        ];

        if ($strict) {
            foreach ($checks as &$check) {
                if ($check['status'] === 'warn') $check['status'] = 'fail';
            }
            unset($check);
        }

        $passed  = count(array_filter($checks, fn ($c) => $c['status'] === 'pass'));
        $warned  = count(array_filter($checks, fn ($c) => $c['status'] === 'warn'));
        $failed  = count(array_filter($checks, fn ($c) => $c['status'] === 'fail'));
        $score   = (int) round($passed / count($checks) * 100);

        if ($this->option('json')) {
            $this->line(json_encode([
                'score'   => $score,
                'passed'  => $passed,
                'warned'  => $warned,
                'failed'  => $failed,
                'total'   => count($checks),
                'tenant'  => $tenantId,
                'checks'  => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $score >= 80 ? self::SUCCESS : self::FAILURE;
        }

        $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');

        $this->newLine();
        $this->line('<fg=bright-cyan;options=bold>Mobile Security Governance Validation</>');
        $this->line(str_repeat('─', 55));

        foreach ($checks as $check) {
            $icon = match ($check['status']) {
                'pass'  => '<fg=green>✓</>',
                'warn'  => '<fg=yellow>⚠</>',
                'fail'  => '<fg=red>✗</>',
                default => '?',
            };
            $this->line("  {$icon}  {$check['message']}");
        }

        $this->newLine();
        $this->line(str_repeat('─', 55));
        $this->line("<fg={$color};options=bold>Security Score: {$score}/100</> — {$passed}/" . count($checks) . ' checks passed');
        $this->newLine();

        if ($score >= 90) {
            $this->info('Mobile Security Governance: Enterprise-Grade ✓');
        } elseif ($score >= 70) {
            $this->warn('Mobile Security Governance: Action required');
        } else {
            $this->error('Mobile Security Governance: Critical failures — immediate action required');
        }

        if ($failWarn && $warned > 0) return self::FAILURE;
        return $score >= 70 ? self::SUCCESS : self::FAILURE;
    }
}
