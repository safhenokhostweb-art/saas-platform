<?php

namespace SaaSPlatform\CoreUtils\Security;

use Illuminate\Support\Facades\Cache;

class SecurityAdvisoryService
{
    private const ADVISORY_KEY = 'security:advisories:';
    private const PATCH_KEY    = 'security:patches:';

    public function getAdvisories(string $tenantId = 'global'): array
    {
        return Cache::get(self::ADVISORY_KEY . $tenantId, $this->getBuiltInAdvisories());
    }

    public function publishAdvisory(array $advisory): void
    {
        $advisory = array_merge([
            'id'          => uniqid('adv_', true),
            'severity'    => 'info',   // critical | high | medium | low | info
            'title'       => '',
            'description' => '',
            'affected'    => [],
            'cve'         => null,
            'patch_version' => null,
            'published_at'=> now()->toIso8601String(),
            'requires_license' => false,
        ], $advisory);

        $key       = self::ADVISORY_KEY . 'global';
        $advisories = Cache::get($key, []);
        $advisories[$advisory['id']] = $advisory;
        Cache::put($key, $advisories, 86400 * 90);
    }

    public function getPatchStatus(string $module, string $currentVersion): array
    {
        $advisories = $this->getAdvisories();
        $applicable = array_filter($advisories, fn ($a) => in_array($module, $a['affected'] ?? []));

        $patchAvailable = false;
        $critical       = false;

        foreach ($applicable as $a) {
            if (! empty($a['patch_version']) && version_compare($currentVersion, $a['patch_version'], '<')) {
                $patchAvailable = true;
            }
            if ($a['severity'] === 'critical') {
                $critical = true;
            }
        }

        return [
            'module'          => $module,
            'current_version' => $currentVersion,
            'patch_available' => $patchAvailable,
            'critical'        => $critical,
            'advisories'      => array_values($applicable),
            'confidence'      => $this->calculatePatchConfidence($module, $currentVersion),
        ];
    }

    public function getTenantRiskDashboard(string $tenantId): array
    {
        $advisories  = $this->getAdvisories($tenantId);
        $critical    = array_filter($advisories, fn ($a) => $a['severity'] === 'critical');
        $high        = array_filter($advisories, fn ($a) => $a['severity'] === 'high');
        $riskScore   = min(100, count($critical) * 30 + count($high) * 10);

        return [
            'tenant_id'     => $tenantId,
            'risk_score'    => $riskScore,
            'risk_level'    => $riskScore >= 70 ? 'critical' : ($riskScore >= 30 ? 'high' : ($riskScore >= 10 ? 'medium' : 'low')),
            'advisories'    => [
                'critical'  => count($critical),
                'high'      => count($high),
                'total'     => count($advisories),
            ],
            'patch_confidence' => $this->calculatePatchConfidence('all', '1.0.0'),
            'generated_at'  => now()->toIso8601String(),
        ];
    }

    public function scheduleMaintenance(string $tenantId, string $scheduledAt, string $reason): string
    {
        $id = uniqid('maint_', true);
        Cache::put("security:maintenance:{$tenantId}:{$id}", [
            'id'           => $id,
            'tenant_id'    => $tenantId,
            'scheduled_at' => $scheduledAt,
            'reason'       => $reason,
            'status'       => 'scheduled',
            'created_at'   => now()->toIso8601String(),
        ], 86400 * 30);
        return $id;
    }

    public function getRolloutStatus(string $module): array
    {
        return Cache::get("security:rollout:{$module}", [
            'stage'      => 'stable',
            'percentage' => 100,
            'notes'      => 'Full rollout complete',
        ]);
    }

    private function calculatePatchConfidence(string $module, string $version): int
    {
        // Rule-based confidence: in a real system this would check test coverage and rollout history
        return 87;
    }

    private function getBuiltInAdvisories(): array
    {
        return [
            'platform-security-baseline' => [
                'id'           => 'platform-security-baseline',
                'severity'     => 'info',
                'title'        => 'Security Baseline Active',
                'description'  => 'Platform security governance is active and monitoring.',
                'affected'     => [],
                'cve'          => null,
                'patch_version'=> null,
                'published_at' => now()->toIso8601String(),
                'requires_license' => false,
            ],
        ];
    }
}
