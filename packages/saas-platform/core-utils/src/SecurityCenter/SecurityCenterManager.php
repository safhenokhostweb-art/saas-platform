<?php

namespace SaaSPlatform\CoreUtils\SecurityCenter;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\SecurityCenter\UpdateChannels\SecurityUpdateChannelPolicy;
use SaaSPlatform\CoreUtils\SecurityCenter\UpdateChannels\RuntimePatchClassificationService;

class SecurityCenterManager
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly SecurityPatchCoordinator $patchCoordinator,
        private readonly RuntimeTrustAnalyzer $trustAnalyzer,
        private readonly TenantSecurityStatusService $tenantStatus,
        private readonly SecurityIntegrityVerifier $integrityVerifier,
        private readonly SecurityCenterTelemetryService $telemetry,
        private readonly SecurityUpdateChannelPolicy $channelPolicy,
        private readonly RuntimePatchClassificationService $patchClassifier,
    ) {}

    public function getSecurityScore(string $tenantId = 'global'): int
    {
        return Cache::remember("security:score:{$tenantId}", self::CACHE_TTL, function () use ($tenantId) {
            $trust      = $this->trustAnalyzer->analyzeTrustScore($tenantId);
            $integrity  = $this->integrityVerifier->getIntegrityScore();
            $patchDebt  = $this->patchCoordinator->getPendingCriticalCount($tenantId);
            $base       = (int)(($trust + $integrity) / 2);
            $penalty    = min(40, $patchDebt * 10);
            return max(0, $base - $penalty);
        });
    }

    public function getTrustScore(string $tenantId = 'global'): int
    {
        return $this->trustAnalyzer->analyzeTrustScore($tenantId);
    }

    public function getDashboardSummary(string $tenantId = 'global'): array
    {
        $score          = $this->getSecurityScore($tenantId);
        $trust          = $this->getTrustScore($tenantId);
        $status         = $this->tenantStatus->getStatus($tenantId);
        $patches        = $this->patchCoordinator->getPatchSummary($tenantId);
        $integrity      = $this->integrityVerifier->getReport();

        return [
            'security_score'          => $score,
            'trust_score'             => $trust,
            'security_grade'          => $this->scoreToGrade($score),
            'trust_level'             => $this->scoreToTrustLevel($trust),
            'last_security_update'    => $status['last_patch_at'] ?? null,
            'runtime_health'          => $status['runtime_health'] ?? 'healthy',
            'installed_patches'       => $patches['installed'] ?? 0,
            'pending_critical'        => $patches['pending_critical'] ?? 0,
            'pending_total'           => $patches['pending_total'] ?? 0,
            'patch_confidence'        => $patches['confidence'] ?? 87,
            'auto_update_enabled'     => $status['auto_update_enabled'] ?? false,
            'rollback_available'      => $status['rollback_available'] ?? true,
            'integrity_verified'      => $integrity['verified'] ?? true,
            'signed_release'          => $integrity['signed'] ?? true,
            'runtime_integrity_state' => $integrity['state'] ?? 'clean',
            'tenant_id'               => $tenantId,
            'generated_at'            => now()->toIso8601String(),
        ];
    }

    public function getPlatformSecurityReport(): array
    {
        $score     = $this->getSecurityScore('global');
        $channels  = $this->channelPolicy->getChannelSummary();
        $integrity = $this->integrityVerifier->getReport();

        return [
            'platform_score'      => $score,
            'channel_summary'     => $channels,
            'integrity_report'    => $integrity,
            'mandatory_updates'   => $this->patchCoordinator->getMandatoryUpdates(),
            'update_policy'       => $this->channelPolicy->getPolicyMatrix(),
            'generated_at'        => now()->toIso8601String(),
        ];
    }

    public function isSecurityChannelEnabled(): bool
    {
        return true; // ALWAYS enabled — mandatory
    }

    public function clearSecurityCache(string $tenantId = 'global'): void
    {
        Cache::forget("security:score:{$tenantId}");
        $this->telemetry->recordEvent('security_cache_cleared', ['tenant_id' => $tenantId]);
    }

    private function scoreToGrade(int $score): string
    {
        return match(true) {
            $score >= 95 => 'A+',
            $score >= 90 => 'A',
            $score >= 80 => 'B+',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            $score >= 50 => 'D',
            default      => 'F',
        };
    }

    private function scoreToTrustLevel(int $score): string
    {
        return match(true) {
            $score >= 90 => 'trusted',
            $score >= 70 => 'verified',
            $score >= 50 => 'conditional',
            default      => 'untrusted',
        };
    }
}
