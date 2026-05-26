<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Trust;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class PluginSignatureVerifier
{
    public function __construct(
        private TrustedPublisherRegistry $publisherRegistry,
        private PluginIntegrityValidator $integrityValidator,
        private TelemetryManager         $telemetry
    ) {}

    public function verify(string $moduleId, string $publisherId, string $modulePath, string $declaredHash): array
    {
        $result = [
            'module_id'         => $moduleId,
            'publisher_id'      => $publisherId,
            'publisher_trusted' => false,
            'hash_valid'        => false,
            'verified'          => false,
        ];

        $result['publisher_trusted'] = $this->publisherRegistry->isTrusted($publisherId);

        if (!$result['publisher_trusted']) {
            $this->telemetry->warning('plugin_governance', 'plugin.signature.untrusted_publisher', [
                'module_id'    => $moduleId,
                'publisher_id' => $publisherId,
            ]);
        }

        $result['hash_valid'] = $this->integrityValidator->verifyHash($modulePath, $declaredHash);

        if (!$result['hash_valid']) {
            $this->telemetry->critical('plugin_governance', 'plugin.signature.hash_mismatch', [
                'module_id' => $moduleId,
                'path'      => $modulePath,
            ]);
        }

        $result['verified'] = $result['publisher_trusted'] && $result['hash_valid'];

        return $result;
    }

    public function verifySource(string $moduleId, string $publisherId, string $source, string $declaredHash): array
    {
        $result = [
            'module_id'         => $moduleId,
            'publisher_id'      => $publisherId,
            'publisher_trusted' => $this->publisherRegistry->isTrusted($publisherId),
            'hash_valid'        => $this->integrityValidator->verifySourceHash($source, $declaredHash),
            'verified'          => false,
        ];

        $result['verified'] = $result['publisher_trusted'] && $result['hash_valid'];
        return $result;
    }
}
