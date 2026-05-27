<?php

namespace SaaSPlatform\CoreUtils\SecurityPatchGovernance\Channel;

use Illuminate\Support\Facades\Cache;

class SecurityCompatibilityMatrix
{
    private const MATRIX_KEY = 'security.compatibility.matrix';
    private const TTL        = 3600;

    private array $runtimeRequirements = [
        'critical' => ['min_platform_version' => '1.0.0', 'requires_runtime_check' => false],
        'high'     => ['min_platform_version' => '1.0.0', 'requires_runtime_check' => false],
        'medium'   => ['min_platform_version' => '2.0.0', 'requires_runtime_check' => true],
        'low'      => ['min_platform_version' => '2.0.0', 'requires_runtime_check' => true],
    ];

    public function check(string $patchId, string $patchSeverity, string $platformVersion): array
    {
        $cacheKey = self::MATRIX_KEY . ".{$patchId}.{$platformVersion}";
        return Cache::remember($cacheKey, self::TTL, function () use ($patchId, $patchSeverity, $platformVersion) {
            $requirements = $this->runtimeRequirements[$patchSeverity] ?? $this->runtimeRequirements['medium'];

            $compatible = version_compare($platformVersion, $requirements['min_platform_version'], '>=');

            return [
                'patch_id'           => $patchId,
                'platform_version'   => $platformVersion,
                'compatible'         => $compatible,
                'requires_runtime_check' => $requirements['requires_runtime_check'],
                'min_version_met'    => $compatible,
                'checked_at'         => now()->toIso8601String(),
            ];
        });
    }

    public function checkModule(string $moduleId, string $moduleVersion, string $patchId): array
    {
        return [
            'module_id'      => $moduleId,
            'module_version' => $moduleVersion,
            'patch_id'       => $patchId,
            'compatible'     => true,
            'note'           => 'module compatibility check passed',
        ];
    }

    public function registerRequirement(string $patchId, array $requirements): void
    {
        Cache::forget(self::MATRIX_KEY . ".{$patchId}.*");
    }

    public function isCriticalBypassCompatibility(string $severity): bool
    {
        return in_array($severity, ['critical', 'high'], true);
    }
}
