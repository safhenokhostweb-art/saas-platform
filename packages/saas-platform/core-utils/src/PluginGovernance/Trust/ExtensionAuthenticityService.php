<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Trust;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class ExtensionAuthenticityService
{
    public function __construct(
        private PluginSignatureVerifier $signatureVerifier,
        private ModuleTrustRegistry     $trustRegistry,
        private TelemetryManager        $telemetry
    ) {}

    public function authenticate(
        string $moduleId,
        string $publisherId,
        string $modulePath,
        string $declaredHash
    ): array {
        $verification = $this->signatureVerifier->verify($moduleId, $publisherId, $modulePath, $declaredHash);

        if ($verification['verified']) {
            $this->trustRegistry->setTrustLevel($moduleId, ModuleTrustRegistry::LEVEL_TRUSTED, 'signature_verified');
        } elseif ($verification['hash_valid']) {
            $this->trustRegistry->setTrustLevel($moduleId, ModuleTrustRegistry::LEVEL_SANDBOXED, 'hash_valid_untrusted_publisher');
        } else {
            $this->trustRegistry->setTrustLevel($moduleId, ModuleTrustRegistry::LEVEL_UNTRUSTED, 'verification_failed');
        }

        $trustLevel = $this->trustRegistry->getTrustLevel($moduleId);

        $this->telemetry->info('plugin_governance', 'plugin.authenticity.evaluated', [
            'module_id'    => $moduleId,
            'trust_level'  => $trustLevel,
            'verified'     => $verification['verified'],
        ]);

        return [
            'module_id'    => $moduleId,
            'trust_level'  => $trustLevel,
            'can_execute'  => $this->trustRegistry->canExecute($moduleId),
            'verification' => $verification,
        ];
    }

    public function trustLevel(string $moduleId): string
    {
        return $this->trustRegistry->getTrustLevel($moduleId);
    }

    public function isTrusted(string $moduleId): bool
    {
        return $this->trustRegistry->isTrusted($moduleId);
    }

    public function markAsCore(string $moduleId): void
    {
        $this->trustRegistry->setTrustLevel($moduleId, ModuleTrustRegistry::LEVEL_CORE, 'core_module');
    }
}
