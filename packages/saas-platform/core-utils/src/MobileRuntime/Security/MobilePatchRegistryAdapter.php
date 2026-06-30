<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Security;

use Illuminate\Support\Facades\Log;

class MobilePatchRegistryAdapter
{
    public function __construct(
        private readonly SecurityPatchRegistry $platformRegistry,
        private readonly MobileSecurityPatchCoordinator $mobileCoordinator,
    ) {
        $this->seedMobileRegistry();
    }

    /**
     * Seeds MobileSecurityPatchCoordinator from the platform SecurityPatchRegistry.
     * Idempotent — safe to call multiple times. Silently skips if cache backend unavailable.
     */
    public function seedMobileRegistry(): void
    {
        try {
            $this->platformRegistry->boot();
            $platformPatches = $this->platformRegistry->all();
            $existing        = $this->mobileCoordinator->getAvailablePatches();

            $seeded = 0;
            foreach ($platformPatches as $id => $patch) {
                if (isset($existing[$id])) {
                    continue;
                }
                $this->mobileCoordinator->registerPatch($id, [
                    'severity'             => $patch['severity'] ?? 'normal',
                    'type'                 => $patch['type'] ?? 'security',
                    'title'                => $patch['title'] ?? $id,
                    'description'          => $patch['description'] ?? '',
                    'applies_to_versions'  => [],    // applies to all versions
                    'source'               => 'platform_registry',
                ]);
                $seeded++;
            }

            if ($seeded > 0) {
                Log::debug('mobile.security.patch_registry_seeded', [
                    'platform_count' => count($platformPatches),
                    'seeded'         => $seeded,
                ]);
            }
        } catch (\Throwable) {
            // Cache backend unavailable — skip boot-time seeding
        }
    }

    public function getPlatformPatches(): array
    {
        return $this->platformRegistry->all();
    }

    public function getMobilePatches(): array
    {
        return $this->mobileCoordinator->getAvailablePatches();
    }

    public function syncStatus(): array
    {
        $platform = count($this->getPlatformPatches());
        $mobile   = count($this->getMobilePatches());

        return [
            'platform_count'  => $platform,
            'mobile_count'    => $mobile,
            'in_sync'         => $mobile >= $platform,
            'drift'           => max(0, $platform - $mobile),
        ];
    }
}
