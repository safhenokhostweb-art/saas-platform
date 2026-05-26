<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Marketplace;

use SaaSPlatform\CoreUtils\PluginGovernance\Trust\ExtensionAuthenticityService;
use SaaSPlatform\CoreUtils\PluginGovernance\Lifecycle\PluginLifecycleManager;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class RuntimeMarketplacePolicy
{
    public function __construct(
        private MarketplaceGovernanceValidator $validator,
        private ExtensionAuthenticityService   $authenticityService,
        private PluginLifecycleManager         $lifecycleManager,
        private TelemetryManager               $telemetry
    ) {}

    public function installModule(MarketplaceModuleDescriptor $descriptor, string $modulePath = ''): array
    {
        $profile = $this->validator->validate($descriptor);

        if (!$profile->isCompatible()) {
            $this->telemetry->warning('plugin_governance', 'plugin.marketplace.install_rejected', [
                'module_id' => $descriptor->moduleId(),
                'reasons'   => $profile->incompatibleReasons(),
            ]);

            return [
                'installed' => false,
                'reasons'   => $profile->incompatibleReasons(),
            ];
        }

        if ($modulePath) {
            $auth = $this->authenticityService->authenticate(
                $descriptor->moduleId(),
                $descriptor->publisherId(),
                $modulePath,
                $descriptor->integrityHash()
            );

            if (!$auth['can_execute']) {
                return ['installed' => false, 'reasons' => ['authenticity_check_failed']];
            }
        }

        $result = $this->lifecycleManager->register(
            $descriptor->moduleId(),
            $descriptor->version(),
            $modulePath
        );

        if ($result['activated']) {
            $this->telemetry->info('plugin_governance', 'plugin.marketplace.installed', [
                'module_id' => $descriptor->moduleId(),
                'version'   => $descriptor->version(),
            ]);
        }

        return $result;
    }

    public function canInstall(MarketplaceModuleDescriptor $descriptor): bool
    {
        return $this->validator->validate($descriptor)->isCompatible();
    }
}
