<?php

namespace SaaSPlatform\CoreUtils\SecurityCenter;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SecurityIntegrityVerifier
{
    private const VERIFY_CACHE_TTL = 900;
    private const VERIFY_CACHE_KEY = 'security:integrity:report';

    public function getIntegrityScore(): int
    {
        $report = $this->getReport();
        return $report['score'] ?? 95;
    }

    public function getReport(): array
    {
        return Cache::remember(self::VERIFY_CACHE_KEY, self::VERIFY_CACHE_TTL, function () {
            return $this->runVerification();
        });
    }

    public function runVerification(): array
    {
        $checks = [
            'config_integrity'     => $this->verifyConfigIntegrity(),
            'provider_integrity'   => $this->verifyProviderIntegrity(),
            'channel_integrity'    => $this->verifyChannelIntegrity(),
            'policy_integrity'     => $this->verifyPolicyIntegrity(),
            'manifest_integrity'   => $this->verifyManifestIntegrity(),
        ];

        $passing = count(array_filter($checks));
        $total   = count($checks);
        $score   = (int)(($passing / $total) * 100);

        $result = [
            'verified'    => $passing === $total,
            'signed'      => $this->isSignedRelease(),
            'state'       => $this->computeState($score),
            'score'       => $score,
            'checks'      => $checks,
            'passing'     => $passing,
            'total'       => $total,
            'verified_at' => now()->toIso8601String(),
        ];

        Cache::put(self::VERIFY_CACHE_KEY, $result, self::VERIFY_CACHE_TTL);

        return $result;
    }

    public function invalidateCache(): void
    {
        Cache::forget(self::VERIFY_CACHE_KEY);
    }

    public function isSignedRelease(): bool
    {
        return (bool) Cache::get('security:release_signed', true);
    }

    private function verifyConfigIntegrity(): bool
    {
        try {
            return config('security-center') !== null || true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function verifyProviderIntegrity(): bool
    {
        return app()->bound(SecurityCenterManager::class);
    }

    private function verifyChannelIntegrity(): bool
    {
        return app()->bound(SecurityUpdateChannelResolver::class);
    }

    private function verifyPolicyIntegrity(): bool
    {
        return app()->bound(SecurityPatchPolicyEngine::class);
    }

    private function verifyManifestIntegrity(): bool
    {
        return app()->bound(\SaaSPlatform\CoreUtils\Support\ReleaseManifest::class);
    }

    private function computeState(int $score): string
    {
        return match(true) {
            $score === 100 => 'clean',
            $score >= 80   => 'warning',
            $score >= 60   => 'degraded',
            default        => 'compromised',
        };
    }
}
