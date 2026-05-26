<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class PluginDependencyScanner
{
    public function __construct(
        private RuntimeDependencyValidator $validator,
        private DependencyRiskClassifier   $classifier,
        private TelemetryManager           $telemetry
    ) {}

    public function scan(string $moduleId, string $modulePath): array
    {
        $report = $this->validator->validateDirectory($modulePath);
        $report['module_id'] = $moduleId;
        $report['scanned_at'] = now()->toIso8601String();

        $this->telemetry->info('plugin_governance', 'plugin.dependency.scan_complete', [
            'module_id'      => $moduleId,
            'scanned_files'  => $report['scanned_files'] ?? 0,
            'issues_found'   => $report['issues_found'] ?? 0,
            'classification' => $report['overall_classification'] ?? DependencyRiskClassifier::SAFE,
        ]);

        if (!$this->classifier->isExecutionAllowed($report['overall_classification'] ?? DependencyRiskClassifier::SAFE)) {
            $this->telemetry->critical('plugin_governance', 'plugin.dependency.execution_blocked', [
                'module_id'      => $moduleId,
                'classification' => $report['overall_classification'],
            ]);
        }

        return $report;
    }

    public function scanSource(string $moduleId, string $sourceCode): array
    {
        return $this->validator->validateSource($sourceCode, $moduleId);
    }

    public function isModuleSafe(string $moduleId, string $modulePath): bool
    {
        $report = $this->scan($moduleId, $modulePath);
        return $this->classifier->isExecutionAllowed($report['overall_classification'] ?? DependencyRiskClassifier::BLOCKED);
    }
}
