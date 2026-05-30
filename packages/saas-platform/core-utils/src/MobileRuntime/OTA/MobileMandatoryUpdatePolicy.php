<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\OTA;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MobileMandatoryUpdatePolicy
{
    private const PREFIX = 'mobile:update_policy:';
    private const TTL    = 86400;

    public function setMandatoryFrom(string $tenantId, string $version, \DateTimeInterface $effectiveFrom): void
    {
        $policy = $this->getMandatoryPolicy($tenantId);
        $policy['mandatory_from_version'] = $version;
        $policy['effective_from']         = $effectiveFrom->format(\DateTimeInterface::ISO8601);
        Cache::put(self::PREFIX . $tenantId, $policy, self::TTL);
    }

    public function getMandatoryPolicy(string $tenantId): array
    {
        return Cache::get(self::PREFIX . $tenantId, [
            'mandatory_from_version' => null,
            'effective_from'         => null,
            'scheduled_updates'      => [],
        ]);
    }

    public function isMandatoryForVersion(string $appVersion, string $tenantId): bool
    {
        $policy = $this->getMandatoryPolicy($tenantId);
        $minVer = $policy['mandatory_from_version'] ?? null;
        if (! $minVer) return false;

        $effectiveFrom = $policy['effective_from'] ?? null;
        if ($effectiveFrom && now()->isBefore($effectiveFrom)) return false;

        return version_compare($appVersion, $minVer, '<');
    }

    public function getEffectiveDate(string $tenantId): ?\DateTimeInterface
    {
        $policy = $this->getMandatoryPolicy($tenantId);
        $date   = $policy['effective_from'] ?? null;
        return $date ? new \DateTime($date) : null;
    }

    public function scheduleUpdate(string $tenantId, string $targetVersion, \DateTimeInterface $deadline): void
    {
        $policy = $this->getMandatoryPolicy($tenantId);
        $updateId = Str::uuid()->toString();
        $policy['scheduled_updates'][$updateId] = [
            'id'             => $updateId,
            'target_version' => $targetVersion,
            'deadline'       => $deadline->format(\DateTimeInterface::ISO8601),
            'created_at'     => now()->toIso8601String(),
        ];
        Cache::put(self::PREFIX . $tenantId, $policy, self::TTL);
    }

    public function getScheduledUpdates(string $tenantId): array
    {
        $policy = $this->getMandatoryPolicy($tenantId);
        return array_values($policy['scheduled_updates'] ?? []);
    }

    public function cancelScheduledUpdate(string $tenantId, string $updateId): void
    {
        $policy = $this->getMandatoryPolicy($tenantId);
        unset($policy['scheduled_updates'][$updateId]);
        Cache::put(self::PREFIX . $tenantId, $policy, self::TTL);
    }

    public function generateNotice(string $tenantId, string $appVersion): array
    {
        if (! $this->isMandatoryForVersion($appVersion, $tenantId)) {
            return ['notice_type' => 'none', 'days_remaining' => null, 'message' => ''];
        }

        $deadline     = $this->getEffectiveDate($tenantId);
        $daysRemaining = $deadline ? max(0, (int) now()->diffInDays($deadline, false)) : 0;

        return [
            'notice_type'    => $daysRemaining > 3 ? 'warning' : 'critical',
            'days_remaining' => $daysRemaining,
            'message'        => $daysRemaining > 0
                ? "Please update within {$daysRemaining} day(s) to continue using the app."
                : 'A mandatory update is required. Please update now.',
        ];
    }
}
