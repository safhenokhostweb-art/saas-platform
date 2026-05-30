<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\OTA;

use Illuminate\Support\Facades\Config;

class MobileUpdateManifestBuilder
{
    public function __construct(
        private readonly MobileVersionEnforcementService $enforcement,
        private readonly MobileFeatureBundleRegistry $bundles,
        private readonly MobileMandatoryUpdatePolicy $mandatoryPolicy,
    ) {}

    public function build(string $tenantId, string $currentVersion, string $channel): array
    {
        $enforcement = $this->enforcement->enforce($currentVersion, $tenantId);

        return [
            'tenant_id'          => $tenantId,
            'current_version'    => $currentVersion,
            'channel'            => $channel,
            'bundles'            => $this->buildBundleList($tenantId),
            'rollout_policy'     => $this->buildRolloutPolicy($tenantId, $channel),
            'critical_patches'   => $this->buildCriticalPatchList($currentVersion),
            'enforcement'        => $enforcement,
            'mandatory_update'   => $enforcement['action'] === 'hard_update',
            'built_at'           => now()->toIso8601String(),
        ];
    }

    public function buildRolloutPolicy(string $tenantId, string $channel): array
    {
        return [
            'channel'         => $channel,
            'rollout_percent' => match ($channel) {
                'canary' => 5,
                'beta'   => 20,
                default  => 100,
            },
            'staged'          => $channel !== 'stable',
        ];
    }

    public function buildBundleList(string $tenantId): array
    {
        return $this->bundles->getBundlesForTenant($tenantId);
    }

    public function buildDeltaManifest(string $fromVersion, string $toVersion): array
    {
        return [
            'from_version' => $fromVersion,
            'to_version'   => $toVersion,
            'delta_type'   => 'full',
            'assets'       => [],
        ];
    }

    public function buildCriticalPatchList(string $currentVersion): array
    {
        return [];
    }

    public function sign(array $manifest): array
    {
        $payload            = json_encode($manifest);
        $secret             = Config::get('app.key', '');
        $manifest['signed_at']  = now()->toIso8601String();
        $manifest['signature']  = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        return $manifest;
    }

    public function verify(array $manifest): bool
    {
        $signature  = $manifest['signature'] ?? '';
        $signedAt   = $manifest['signed_at'] ?? '';
        unset($manifest['signature'], $manifest['signed_at']);
        $payload    = json_encode($manifest);
        $secret     = Config::get('app.key', '');
        $expected   = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        return hash_equals($expected, $signature);
    }
}
