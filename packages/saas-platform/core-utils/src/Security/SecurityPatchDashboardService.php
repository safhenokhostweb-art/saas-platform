<?php

namespace SaaSPlatform\CoreUtils\Security;

use Illuminate\Support\Facades\Cache;

class SecurityPatchDashboardService
{
    public function getActivePatchWarnings(): array
    {
        $advisories = app(SecurityAdvisoryService::class)->getActiveAdvisories();
        $warnings   = [];

        foreach ($advisories as $advisory) {
            $severity = $advisory['severity'] ?? 'low';
            if (in_array($severity, ['critical', 'high', 'medium'])) {
                $warnings[] = [
                    'id'          => $advisory['id'] ?? uniqid(),
                    'severity'    => $severity,
                    'title'       => $advisory['title'] ?? 'Security Advisory',
                    'module'      => $advisory['module'] ?? 'platform',
                    'action'      => $advisory['action'] ?? 'Update recommended',
                    'forced'      => $severity === 'critical',
                    'bypasses_license' => $advisory['bypasses_license'] ?? ($severity === 'critical'),
                    'published_at' => $advisory['published_at'] ?? now()->toISOString(),
                ];
            }
        }

        usort($warnings, fn($a, $b) => $this->severityWeight($b['severity']) <=> $this->severityWeight($a['severity']));
        return $warnings;
    }

    public function getRiskScore(): array
    {
        $warnings = $this->getActivePatchWarnings();
        $score    = 100;
        $factors  = [];

        foreach ($warnings as $w) {
            $deduction = match($w['severity']) {
                'critical' => 30,
                'high'     => 15,
                'medium'   => 5,
                default    => 2,
            };
            $score -= $deduction;
            $factors[] = "{$w['severity']}: {$w['title']}";
        }

        $score = max(0, $score);
        return [
            'score'   => $score,
            'grade'   => $score >= 90 ? 'A' : ($score >= 70 ? 'B' : ($score >= 50 ? 'C' : 'F')),
            'factors' => $factors,
            'warnings_count' => count($warnings),
        ];
    }

    public function getStagedRolloutStatus(): array
    {
        return Cache::get('security:staged_rollout', [
            'active'      => false,
            'patch_id'    => null,
            'progress'    => 0,
            'tenant_count' => 0,
            'started_at'  => null,
        ]);
    }

    public function getModuleVulnerabilityNotices(): array
    {
        $notices = [];
        $modules = ['modules-forms', 'modules-tickets', 'core-utils'];
        foreach ($modules as $module) {
            $vulns = Cache::get("security:vulns:{$module}", []);
            if (! empty($vulns)) {
                $notices[$module] = $vulns;
            }
        }
        return $notices;
    }

    public function getUpgradeImpactAnalysis(string $patchId): array
    {
        return Cache::get("security:impact:{$patchId}", [
            'patch_id'       => $patchId,
            'breaking_changes' => [],
            'affected_tenants' => 0,
            'estimated_downtime_s' => 0,
            'rollback_safe'  => true,
            'analysis'       => 'No impact data available — run analysis first.',
        ]);
    }

    private function severityWeight(string $severity): int
    {
        return match($severity) {
            'critical' => 4,
            'high'     => 3,
            'medium'   => 2,
            'low'      => 1,
            default    => 0,
        };
    }
}
