<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning;

class UnsafeImportDetector
{
    private const BLOCKED_PATTERNS = [
        'App\\'         => ['severity' => DependencyRiskClassifier::BLOCKED, 'reason' => 'Direct App\\ import violates package boundary'],
        'exec('         => ['severity' => DependencyRiskClassifier::BLOCKED, 'reason' => 'Shell execution function'],
        'shell_exec('   => ['severity' => DependencyRiskClassifier::BLOCKED, 'reason' => 'Shell execution function'],
        'system('       => ['severity' => DependencyRiskClassifier::BLOCKED, 'reason' => 'Shell execution function'],
        'passthru('     => ['severity' => DependencyRiskClassifier::BLOCKED, 'reason' => 'Shell execution function'],
        'proc_open('    => ['severity' => DependencyRiskClassifier::BLOCKED, 'reason' => 'Process execution'],
        'popen('        => ['severity' => DependencyRiskClassifier::BLOCKED, 'reason' => 'Process execution'],
        'eval('         => ['severity' => DependencyRiskClassifier::BLOCKED, 'reason' => 'Dynamic code execution'],
    ];

    private const UNSAFE_PATTERNS = [
        'DB::statement'    => ['severity' => DependencyRiskClassifier::UNSAFE, 'reason' => 'Direct DB statement execution'],
        'DB::unprepared'   => ['severity' => DependencyRiskClassifier::UNSAFE, 'reason' => 'Unprepared DB query'],
        'Schema::'         => ['severity' => DependencyRiskClassifier::UNSAFE, 'reason' => 'Direct schema manipulation'],
        'Artisan::call'    => ['severity' => DependencyRiskClassifier::UNSAFE, 'reason' => 'Direct Artisan invocation'],
    ];

    private const WARNING_PATTERNS = [
        'file_get_contents' => ['severity' => DependencyRiskClassifier::WARNING, 'reason' => 'Filesystem access without capability check'],
        'file_put_contents' => ['severity' => DependencyRiskClassifier::WARNING, 'reason' => 'Filesystem write without capability check'],
        'fopen('            => ['severity' => DependencyRiskClassifier::WARNING, 'reason' => 'Filesystem access without capability check'],
        'curl_init('        => ['severity' => DependencyRiskClassifier::WARNING, 'reason' => 'Direct HTTP request without capability check'],
        'Http::withoutVerifying' => ['severity' => DependencyRiskClassifier::WARNING, 'reason' => 'HTTP without TLS verification'],
        'SiteTenantScope'   => ['severity' => DependencyRiskClassifier::WARNING, 'reason' => 'Direct tenant scope bypass attempt'],
        'withoutTenantIsolation' => ['severity' => DependencyRiskClassifier::WARNING, 'reason' => 'Tenant isolation bypass'],
    ];

    public function scan(string $sourceCode): array
    {
        $findings = [];

        $allPatterns = array_merge(
            self::BLOCKED_PATTERNS,
            self::UNSAFE_PATTERNS,
            self::WARNING_PATTERNS
        );

        foreach ($allPatterns as $pattern => $meta) {
            if (str_contains($sourceCode, $pattern)) {
                $findings[] = [
                    'pattern'  => $pattern,
                    'severity' => $meta['severity'],
                    'reason'   => $meta['reason'],
                ];
            }
        }

        return $findings;
    }

    public function scanFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            return [];
        }

        return $this->scan($content);
    }
}
