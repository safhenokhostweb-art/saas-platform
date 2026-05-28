<?php

namespace SaaSPlatform\CoreUtils\SecurityCenter\UpdateChannels;

use SaaSPlatform\CoreUtils\SecurityCenter\SecurityUpdateChannelResolver;

class RuntimePatchClassificationService
{
    public function __construct(
        private readonly SecurityUpdateChannelResolver $channelResolver,
    ) {}

    public function classify(array $patch): array
    {
        $channel    = $this->channelResolver->resolveFromPatch($patch);
        $severity   = $this->classifySeverity($patch);
        $urgency    = $this->classifyUrgency($patch, $channel, $severity);
        $risk       = $this->classifyRisk($patch);
        $confidence = $this->estimateConfidence($patch);

        return array_merge($patch, [
            'channel'    => $channel,
            'severity'   => $severity,
            'urgency'    => $urgency,
            'risk'       => $risk,
            'confidence' => $confidence,
            'classified_at' => now()->toIso8601String(),
        ]);
    }

    public function classifyBatch(array $patches): array
    {
        return array_map(fn ($p) => $this->classify($p), $patches);
    }

    public function getClassificationSummary(array $patches): array
    {
        $classified = $this->classifyBatch($patches);
        $byChannel  = [];
        $bySeverity = [];

        foreach ($classified as $patch) {
            $ch = $patch['channel'] ?? 'stability';
            $sv = $patch['severity'] ?? 'medium';
            $byChannel[$ch]  = ($byChannel[$ch] ?? 0) + 1;
            $bySeverity[$sv] = ($bySeverity[$sv] ?? 0) + 1;
        }

        return [
            'total'       => count($classified),
            'by_channel'  => $byChannel,
            'by_severity' => $bySeverity,
            'urgent'      => count(array_filter($classified, fn ($p) => $p['urgency'] === 'immediate')),
        ];
    }

    private function classifySeverity(array $patch): string
    {
        if (! empty($patch['severity'])) {
            return strtolower($patch['severity']);
        }

        $channel = $patch['channel'] ?? '';
        if (in_array($channel, ['critical', 'hotfix'])) {
            return 'critical';
        }
        if ($channel === 'security') {
            return 'high';
        }
        return 'medium';
    }

    private function classifyUrgency(array $patch, string $channel, string $severity): string
    {
        if ($severity === 'critical' || $channel === 'hotfix') {
            return 'immediate';
        }
        if ($channel === 'security' || $severity === 'high') {
            return 'urgent';
        }
        if ($channel === 'stability') {
            return 'normal';
        }
        return 'deferred';
    }

    private function classifyRisk(array $patch): string
    {
        if (! ($patch['rollback_safe'] ?? true)) {
            return 'high';
        }
        if (! ($patch['signed'] ?? false)) {
            return 'medium';
        }
        $confidence = $patch['confidence'] ?? 87;
        if ($confidence < 70) {
            return 'high';
        }
        return 'low';
    }

    private function estimateConfidence(array $patch): int
    {
        if (isset($patch['confidence'])) {
            return (int) $patch['confidence'];
        }

        $score = 85;
        if ($patch['signed'] ?? false)       $score += 5;
        if ($patch['rollback_safe'] ?? true) $score += 5;
        if ($patch['tested'] ?? false)       $score += 5;
        return min(100, $score);
    }
}
