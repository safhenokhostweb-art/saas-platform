<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\ResourceGovernance;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class PluginQuotaManager
{
    public function __construct(
        private PluginResourceBudget             $budget,
        private ExtensionRuntimePressureAnalyzer $pressureAnalyzer,
        private TelemetryManager                 $telemetry
    ) {}

    public function checkJobQuota(string $moduleId): bool
    {
        $allowed = $this->budget->canConsumeJob($moduleId);

        if (!$allowed) {
            $this->telemetry->warning('plugin_governance', 'plugin.quota.job_limit_exceeded', [
                'module_id'      => $moduleId,
                'remaining_jobs' => $this->budget->remainingJobs($moduleId),
            ]);
        }

        return $allowed;
    }

    public function checkHttpQuota(string $moduleId): bool
    {
        $allowed = $this->budget->canConsumeHttp($moduleId);

        if (!$allowed) {
            $this->telemetry->warning('plugin_governance', 'plugin.quota.http_limit_exceeded', [
                'module_id'       => $moduleId,
                'remaining_http'  => $this->budget->remainingHttp($moduleId),
            ]);
        }

        return $allowed;
    }

    public function consumeJobQuota(string $moduleId): bool
    {
        return $this->budget->consumeJob($moduleId);
    }

    public function consumeHttpQuota(string $moduleId): bool
    {
        return $this->budget->consumeHttp($moduleId);
    }

    public function quotaSnapshot(string $moduleId): array
    {
        return array_merge($this->budget->snapshot($moduleId), [
            'pressure_level' => $this->pressureAnalyzer->pressureLevel($moduleId),
        ]);
    }
}
