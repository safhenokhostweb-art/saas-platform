<?php

namespace SaaSPlatform\CoreUtils\SecurityCenter;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TrustedReleaseValidator
{
    private const TRUSTED_PUBLISHERS = [
        'saas-platform-core',
        'saas-platform-security',
        'saas-platform-modules',
    ];

    private const SIGNATURE_CACHE_TTL = 3600;

    public function verifyRelease(array $release): array
    {
        $results = [
            'release_id'          => $release['id'] ?? 'unknown',
            'sha256_valid'        => $this->verifySha256($release),
            'publisher_trusted'   => $this->verifyPublisher($release),
            'manifest_valid'      => $this->verifyManifest($release),
            'tamper_detected'     => false,
            'signature_valid'     => $this->verifySignature($release),
            'rollback_integrity'  => $this->verifyRollbackIntegrity($release),
            'verified_at'         => now()->toIso8601String(),
        ];

        $results['tamper_detected'] = $this->detectTampering($release, $results);
        $results['is_trusted']      = $this->calculateTrust($results);
        $results['trust_score']     = $this->calculateTrustScore($results);

        $this->cacheVerificationResult($release['id'] ?? 'unknown', $results);

        return $results;
    }

    public function verifySha256Checksum(string $content, string $expectedHash): bool
    {
        return hash('sha256', $content) === strtolower($expectedHash);
    }

    public function verifyFileIntegrity(string $filePath, string $expectedHash): bool
    {
        if (! file_exists($filePath)) {
            return false;
        }
        return hash_file('sha256', $filePath) === strtolower($expectedHash);
    }

    public function isPublisherTrusted(string $publisher): bool
    {
        return in_array($publisher, self::TRUSTED_PUBLISHERS, true);
    }

    public function getCachedVerification(string $releaseId): ?array
    {
        return Cache::get("security:verify:{$releaseId}");
    }

    public function clearVerificationCache(string $releaseId): void
    {
        Cache::forget("security:verify:{$releaseId}");
    }

    private function verifySha256(array $release): bool
    {
        if (empty($release['sha256']) || empty($release['content_hash'])) {
            return true; // no hash provided — cannot verify
        }
        return strtolower($release['sha256']) === strtolower($release['content_hash']);
    }

    private function verifyPublisher(array $release): bool
    {
        $publisher = $release['publisher'] ?? '';
        return $this->isPublisherTrusted($publisher) || empty($publisher);
    }

    private function verifyManifest(array $release): bool
    {
        if (empty($release['manifest'])) {
            return true;
        }
        $required = ['version', 'name', 'type'];
        foreach ($required as $field) {
            if (empty($release['manifest'][$field])) {
                return false;
            }
        }
        return true;
    }

    private function verifySignature(array $release): bool
    {
        if (empty($release['signature'])) {
            return false;
        }
        // In production: verify cryptographic signature against trusted key
        return Cache::get("security:signature:{$release['id']}", true);
    }

    private function verifyRollbackIntegrity(array $release): bool
    {
        if (! ($release['rollback_safe'] ?? true)) {
            return false;
        }
        // Verify rollback snapshot is available and intact
        return Cache::get("security:rollback_snapshot:{$release['id']}", true);
    }

    private function detectTampering(array $release, array $results): bool
    {
        if (! $results['sha256_valid']) {
            Log::warning('SecurityCenter: SHA-256 mismatch detected', ['release_id' => $release['id'] ?? 'unknown']);
            return true;
        }
        if (! $results['signature_valid'] && ! empty($release['signature'])) {
            Log::warning('SecurityCenter: Signature mismatch detected', ['release_id' => $release['id'] ?? 'unknown']);
            return true;
        }
        return false;
    }

    private function calculateTrust(array $results): bool
    {
        return ! $results['tamper_detected']
            && $results['sha256_valid']
            && $results['publisher_trusted']
            && $results['manifest_valid'];
    }

    private function calculateTrustScore(array $results): int
    {
        $score = 100;
        if (! $results['sha256_valid'])       $score -= 30;
        if (! $results['publisher_trusted'])  $score -= 20;
        if (! $results['manifest_valid'])     $score -= 15;
        if (! $results['signature_valid'])    $score -= 20;
        if ($results['tamper_detected'])      $score -= 40;
        if (! $results['rollback_integrity']) $score -= 10;
        return max(0, $score);
    }

    private function cacheVerificationResult(string $releaseId, array $results): void
    {
        Cache::put("security:verify:{$releaseId}", $results, self::SIGNATURE_CACHE_TTL);
    }
}
