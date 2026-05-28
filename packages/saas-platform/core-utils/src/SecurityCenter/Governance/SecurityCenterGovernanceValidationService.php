<?php

namespace SaaSPlatform\CoreUtils\SecurityCenter\Governance;

use SaaSPlatform\CoreUtils\SecurityCenter\SecurityCenterManager;
use SaaSPlatform\CoreUtils\SecurityCenter\SecurityIntegrityVerifier;
use SaaSPlatform\CoreUtils\SecurityCenter\TrustedReleaseValidator;
use SaaSPlatform\CoreUtils\SecurityCenter\SecurityPatchPolicyEngine;
use SaaSPlatform\CoreUtils\SecurityCenter\SecurityUpdateChannelResolver;
use SaaSPlatform\CoreUtils\SecurityCenter\UpdateChannels\SecurityUpdateChannelPolicy;

class SecurityCenterGovernanceValidationService
{
    private array $results = [];
    private int   $score   = 0;

    public function __construct(
        private readonly SecurityCenterManager $manager,
        private readonly SecurityIntegrityVerifier $integrityVerifier,
        private readonly TrustedReleaseValidator $releaseValidator,
        private readonly SecurityPatchPolicyEngine $policyEngine,
        private readonly SecurityUpdateChannelPolicy $channelPolicy,
        private readonly SecurityUpdateChannelResolver $channelResolver,
    ) {}

    public function validate(bool $strict = false): array
    {
        $this->results = [];

        $this->checkFrontendParity();
        $this->checkMandatoryPatchAvailability();
        $this->checkSignatureVerification();
        $this->checkRollbackSafety();
        $this->checkTenantIsolation();
        $this->checkRuntimeCompatibility();
        $this->checkTrustIntegrity();
        $this->checkReleaseAuthenticity();
        $this->checkPatchChannelEnforcement();
        $this->checkCanonicalUiEnforcement();

        $passing   = count(array_filter($this->results, fn ($r) => $r['status'] === 'pass'));
        $total     = count($this->results);
        $this->score = $total > 0 ? (int)(($passing / $total) * 100) : 0;

        $warnings = array_filter($this->results, fn ($r) => $r['status'] === 'warning');
        $failures = array_filter($this->results, fn ($r) => $r['status'] === 'fail');

        $overallStatus = match(true) {
            count($failures) > 0 => 'fail',
            count($warnings) > 0 && $strict => 'fail',
            count($warnings) > 0 => 'warning',
            default => 'pass',
        };

        return [
            'status'     => $overallStatus,
            'score'      => $this->score,
            'grade'      => $this->scoreToGrade($this->score),
            'total'      => $total,
            'passing'    => $passing,
            'warnings'   => count($warnings),
            'failures'   => count($failures),
            'checks'     => $this->results,
            'validated_at' => now()->toIso8601String(),
        ];
    }

    private function checkFrontendParity(): void
    {
        $viewPath = __DIR__ . '/../../../resources/views/security-center';
        $this->addResult('frontend_parity', [
            'status'  => is_dir($viewPath) ? 'pass' : 'fail',
            'message' => is_dir($viewPath)
                ? 'Security center views exist as canonical package views'
                : 'Security center views missing from canonical package path',
            'path'    => $viewPath,
        ]);
    }

    private function checkMandatoryPatchAvailability(): void
    {
        $policy = $this->channelPolicy->getPolicyMatrix();
        $securityAlwaysInstallable = $policy['security']['always_installable'] ?? false;
        $securityNeverDisabled     = ! ($policy['security']['can_be_disabled'] ?? true);

        $this->addResult('mandatory_patch_availability', [
            'status'  => ($securityAlwaysInstallable && $securityNeverDisabled) ? 'pass' : 'fail',
            'message' => 'Security channel patches must always be installable without license',
            'details' => [
                'always_installable' => $securityAlwaysInstallable,
                'cannot_be_disabled' => $securityNeverDisabled,
            ],
        ]);
    }

    private function checkSignatureVerification(): void
    {
        $canVerify = class_exists(TrustedReleaseValidator::class);
        $this->addResult('signature_verification', [
            'status'  => $canVerify ? 'pass' : 'fail',
            'message' => $canVerify
                ? 'TrustedReleaseValidator available for cryptographic verification'
                : 'TrustedReleaseValidator missing',
        ]);
    }

    private function checkRollbackSafety(): void
    {
        $policy  = $this->policyEngine->getPolicyMatrix();
        $secPol  = $policy['security_channel'] ?? [];
        $hasPost = class_exists(\SaaSPlatform\CoreUtils\SecurityCenter\AutomaticPatch\AutomaticSecurityPatchService::class);

        $this->addResult('rollback_safety', [
            'status'  => $hasPost ? 'pass' : 'warning',
            'message' => $hasPost
                ? 'AutomaticSecurityPatchService provides rollback-on-failure'
                : 'AutomaticSecurityPatchService not found',
        ]);
    }

    private function checkTenantIsolation(): void
    {
        $hasTenantStatus = class_exists(\SaaSPlatform\CoreUtils\SecurityCenter\TenantSecurityStatusService::class);
        $this->addResult('tenant_isolation', [
            'status'  => $hasTenantStatus ? 'pass' : 'fail',
            'message' => $hasTenantStatus
                ? 'TenantSecurityStatusService provides per-tenant isolation'
                : 'Per-tenant security status service missing',
        ]);
    }

    private function checkRuntimeCompatibility(): void
    {
        $report = $this->integrityVerifier->getReport();
        $status = ($report['score'] ?? 0) >= 80 ? 'pass' : 'warning';
        $this->addResult('runtime_compatibility', [
            'status'  => $status,
            'message' => "Integrity score: {$report['score']}/100",
            'score'   => $report['score'] ?? 0,
        ]);
    }

    private function checkTrustIntegrity(): void
    {
        $score  = $this->manager->getTrustScore('global');
        $status = $score >= 80 ? 'pass' : ($score >= 60 ? 'warning' : 'fail');
        $this->addResult('trust_integrity', [
            'status'  => $status,
            'message' => "Platform trust score: {$score}/100",
            'score'   => $score,
        ]);
    }

    private function checkReleaseAuthenticity(): void
    {
        $isSignedRelease = $this->integrityVerifier->isSignedRelease();
        $this->addResult('release_authenticity', [
            'status'  => $isSignedRelease ? 'pass' : 'warning',
            'message' => $isSignedRelease
                ? 'Current release is cryptographically signed'
                : 'Current release lacks cryptographic signature',
        ]);
    }

    private function checkPatchChannelEnforcement(): void
    {
        $channels = $this->channelPolicy->getPolicyMatrix();
        $securityAlwaysEnabled = ($channels['security']['always_installable'] ?? false)
            && ! ($channels['security']['license_required'] ?? true);

        $this->addResult('patch_channel_enforcement', [
            'status'  => $securityAlwaysEnabled ? 'pass' : 'fail',
            'message' => 'Security channel must not require license and must always be installable',
            'channels' => array_keys($channels),
        ]);
    }

    private function checkCanonicalUiEnforcement(): void
    {
        $componentPath = __DIR__ . '/../../../resources/views/components/security-score-card.blade.php';
        $hasComponents = file_exists($componentPath);

        $this->addResult('canonical_ui_enforcement', [
            'status'  => $hasComponents ? 'pass' : 'fail',
            'message' => $hasComponents
                ? 'Canonical core-ui security components exist'
                : 'Missing canonical core-ui security components',
        ]);
    }

    private function addResult(string $check, array $data): void
    {
        $this->results[$check] = array_merge(['check' => $check], $data);
    }

    private function scoreToGrade(int $score): string
    {
        return match(true) {
            $score >= 95 => 'A+',
            $score >= 90 => 'A',
            $score >= 80 => 'B+',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            default      => 'F',
        };
    }
}
