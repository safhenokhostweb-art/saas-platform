<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Lifecycle;

use SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning\PluginDependencyScanner;
use SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning\DependencyRiskClassifier;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class PluginUpgradeValidator
{
    public function __construct(
        private PluginDependencyScanner $scanner,
        private TelemetryManager        $telemetry
    ) {}

    public function validateUpgrade(string $moduleId, string $fromVersion, string $toVersion, string $newPath = ''): array
    {
        $result = [
            'module_id'    => $moduleId,
            'from_version' => $fromVersion,
            'to_version'   => $toVersion,
            'allowed'      => true,
            'reasons'      => [],
        ];

        if ($newPath) {
            $scanReport = $this->scanner->scan($moduleId, $newPath);

            if (!in_array($scanReport['overall_classification'] ?? DependencyRiskClassifier::BLOCKED,
                [DependencyRiskClassifier::SAFE, DependencyRiskClassifier::WARNING], true)) {
                $result['allowed']    = false;
                $result['reasons'][]  = 'dependency_scan_failed: ' . ($scanReport['overall_classification'] ?? 'unknown');
            }
        }

        if (!$this->isVersionCompatible($fromVersion, $toVersion)) {
            $result['reasons'][] = 'major_version_jump_requires_manual_approval';
            $result['allowed']   = false;
        }

        $this->telemetry->info('plugin_governance', 'plugin.upgrade.validated', [
            'module_id'    => $moduleId,
            'from_version' => $fromVersion,
            'to_version'   => $toVersion,
            'allowed'      => $result['allowed'],
        ]);

        return $result;
    }

    private function isVersionCompatible(string $from, string $to): bool
    {
        $fromParts = explode('.', $from);
        $toParts   = explode('.', $to);

        $fromMajor = (int) ($fromParts[0] ?? 0);
        $toMajor   = (int) ($toParts[0] ?? 0);

        // Major version jumps require manual approval
        return abs($toMajor - $fromMajor) <= 1;
    }
}
