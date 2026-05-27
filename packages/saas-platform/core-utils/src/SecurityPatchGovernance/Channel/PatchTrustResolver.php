<?php

namespace SaaSPlatform\CoreUtils\SecurityPatchGovernance\Channel;

use SaaSPlatform\CoreUtils\SecurityPatchGovernance\Trust\ReleaseSignatureManager;
use SaaSPlatform\CoreUtils\SecurityPatchGovernance\Trust\TamperDetectionEngine;
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\TrustedPublisherRegistry;

class PatchTrustResolver
{
    private const TRUST_LEVELS = ['core', 'trusted', 'sandboxed', 'unverified'];

    public function __construct(
        private ReleaseSignatureManager  $signatureManager,
        private TamperDetectionEngine    $tamperDetection,
        private TrustedPublisherRegistry $publisherRegistry,
        private SecurityPatchChannelManager $channelManager
    ) {}

    public function resolve(string $patchId, string $channel, ?string $publisherId, ?string $signature): array
    {
        $channelConfig = $this->channelManager->resolve($channel);
        $requiresSignature = $channelConfig['requires_signature'] ?? true;

        $result = [
            'patch_id'         => $patchId,
            'channel'          => $channel,
            'trust_level'      => 'unverified',
            'publisher_trusted' => false,
            'signature_valid'  => false,
            'tamper_detected'  => false,
            'resolved_at'      => now()->toIso8601String(),
        ];

        if ($publisherId) {
            $result['publisher_trusted'] = $this->publisherRegistry->isTrusted($publisherId);
        }

        if ($requiresSignature && $signature) {
            $result['signature_valid'] = $this->signatureManager->verify($patchId, $signature);
        } elseif (!$requiresSignature) {
            $result['signature_valid'] = true;
        }

        $tamperResult = $this->tamperDetection->scan($patchId);
        $result['tamper_detected'] = $tamperResult['detected'];

        if ($result['tamper_detected']) {
            $result['trust_level'] = 'unverified';
            return $result;
        }

        $result['trust_level'] = $this->determineTrustLevel(
            $channelConfig['trust_level'] ?? 'sandboxed',
            $result['publisher_trusted'],
            $result['signature_valid']
        );

        return $result;
    }

    public function isTrustedForDelivery(array $trustResult): bool
    {
        if ($trustResult['tamper_detected']) {
            return false;
        }
        return in_array($trustResult['trust_level'], ['core', 'trusted'], true)
            && $trustResult['signature_valid'];
    }

    private function determineTrustLevel(string $channelLevel, bool $publisherTrusted, bool $signatureValid): string
    {
        if (!$signatureValid) {
            return 'unverified';
        }
        if ($channelLevel === 'core' && $publisherTrusted) {
            return 'core';
        }
        if ($publisherTrusted && $signatureValid) {
            return 'trusted';
        }
        if ($signatureValid) {
            return 'sandboxed';
        }
        return 'unverified';
    }
}
