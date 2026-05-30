<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Governance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class MobileRuntimeCompatibilityEngine
{
    private const PREFIX     = 'mobile:compat:';
    private const TTL        = 3600;
    private const MIN_DEFAULT = '1.0.0';
    private const MAX_DEFAULT = '99.99.99';

    public function isCompatible(string $appVersion, string $tenantId): bool
    {
        $report = $this->getCompatibilityReport($appVersion, $tenantId);
        return $report['compatible'];
    }

    public function getCompatibilityReport(string $appVersion, string $tenantId): array
    {
        $min      = $this->getMinSupportedVersion($tenantId);
        $max      = $this->getMaxSupportedVersion();
        $warnings = [];

        $cmpMin = $this->compareVersions($appVersion, $min);
        $cmpMax = $this->compareVersions($appVersion, $max);

        $compatible = $cmpMin >= 0 && $cmpMax <= 0;
        $action     = 'none';

        if ($cmpMin < 0) {
            $action   = 'hard_update';
            $warnings[] = "App version {$appVersion} is below minimum supported {$min}";
        } elseif ($cmpMax > 0) {
            $action   = 'unknown';
            $warnings[] = "App version {$appVersion} exceeds known maximum {$max}";
        } elseif ($this->compareVersions($appVersion, $this->getRecommendedVersion($tenantId)) < 0) {
            $action   = 'soft_update';
            $warnings[] = 'A newer version is available';
        }

        return [
            'compatible'    => $compatible,
            'app_version'   => $appVersion,
            'min_version'   => $min,
            'max_version'   => $max,
            'action'        => $action,
            'warnings'      => $warnings,
        ];
    }

    public function getMinSupportedVersion(string $tenantId): string
    {
        return Cache::get(self::PREFIX . "min:{$tenantId}")
            ?? Config::get('mobile.min_app_version', self::MIN_DEFAULT);
    }

    public function getMaxSupportedVersion(): string
    {
        return Config::get('mobile.max_app_version', self::MAX_DEFAULT);
    }

    public function getRecommendedVersion(string $tenantId): string
    {
        return Cache::get(self::PREFIX . "recommended:{$tenantId}")
            ?? Config::get('mobile.recommended_app_version', '1.0.0');
    }

    public function setMinVersion(string $tenantId, string $version): void
    {
        Cache::put(self::PREFIX . "min:{$tenantId}", $version, self::TTL);
    }

    public function setRecommendedVersion(string $tenantId, string $version): void
    {
        Cache::put(self::PREFIX . "recommended:{$tenantId}", $version, self::TTL);
    }

    public function parseVersion(string $version): array
    {
        $parts = explode('.', ltrim($version, 'vV'));
        return [
            'major' => (int) ($parts[0] ?? 0),
            'minor' => (int) ($parts[1] ?? 0),
            'patch' => (int) ($parts[2] ?? 0),
        ];
    }

    public function compareVersions(string $a, string $b): int
    {
        $va = $this->parseVersion($a);
        $vb = $this->parseVersion($b);

        foreach (['major', 'minor', 'patch'] as $segment) {
            if ($va[$segment] !== $vb[$segment]) {
                return $va[$segment] > $vb[$segment] ? 1 : -1;
            }
        }
        return 0;
    }
}
