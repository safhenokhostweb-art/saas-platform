<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Marketplace;

use SaaSPlatform\CoreUtils\PluginGovernance\ModuleCapabilityRegistry;
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\TrustedPublisherRegistry;
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\PluginIntegrityValidator;

class MarketplaceGovernanceValidator
{
    private const MIN_PLATFORM_VERSION = '8.0.0';

    public function __construct(
        private ModuleCapabilityRegistry $capabilityRegistry,
        private TrustedPublisherRegistry $publisherRegistry,
        private PluginIntegrityValidator $integrityValidator
    ) {}

    public function validate(MarketplaceModuleDescriptor $descriptor): PluginCompatibilityProfile
    {
        $missing   = [];
        $reasons   = [];

        foreach ($descriptor->requiredCapabilities() as $capability) {
            if (!$this->capabilityRegistry->has($capability)) {
                $missing[] = $capability;
                $reasons[] = "Required capability [{$capability}] not registered in platform";
            }
        }

        if (!$this->publisherRegistry->isTrusted($descriptor->publisherId())) {
            $reasons[] = "Publisher [{$descriptor->publisherId()}] is not in trusted publisher registry";
        }

        if (!$this->isPlatformVersionCompatible($descriptor->minPlatformVersion())) {
            $reasons[] = "Requires platform {$descriptor->minPlatformVersion()}, current is " . self::MIN_PLATFORM_VERSION;
        }

        return new PluginCompatibilityProfile(
            $descriptor->moduleId(),
            empty($reasons),
            $missing,
            $reasons,
            self::MIN_PLATFORM_VERSION
        );
    }

    public function validateManifest(ExtensionDistributionManifest $manifest, string $modulePath = ''): array
    {
        $issues = [];

        if (empty($manifest->integrityHash())) {
            $issues[] = 'Missing integrity hash in manifest';
        }

        if (!$this->publisherRegistry->isTrusted($manifest->publisherId())) {
            $issues[] = "Publisher [{$manifest->publisherId()}] not trusted";
        }

        if ($modulePath && $manifest->integrityHash()) {
            if (!$this->integrityValidator->verifyHash($modulePath, $manifest->integrityHash())) {
                $issues[] = 'Integrity hash mismatch — module may have been tampered';
            }
        }

        return [
            'valid'  => empty($issues),
            'issues' => $issues,
        ];
    }

    private function isPlatformVersionCompatible(string $required): bool
    {
        return version_compare(self::MIN_PLATFORM_VERSION, $required, '>=');
    }
}
