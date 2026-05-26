<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Metrics;

use SaaSPlatform\CoreUtils\PluginGovernance\Trust\ModuleTrustRegistry;
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\TrustedPublisherRegistry;
use SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning\DependencyRiskClassifier;

final class ExtensionTrustScore
{
    public function __construct(
        private readonly string $moduleId,
        private readonly string $trustLevel,
        private readonly bool   $publisherTrusted,
        private readonly string $dependencyClassification,
        private readonly int    $score
    ) {}

    public static function compute(
        string $moduleId,
        ModuleTrustRegistry     $trustRegistry,
        TrustedPublisherRegistry $publisherRegistry,
        string $publisherId,
        string $dependencyClassification = DependencyRiskClassifier::SAFE
    ): self {
        $trustLevel       = $trustRegistry->getTrustLevel($moduleId);
        $publisherTrusted = $publisherRegistry->isTrusted($publisherId);

        $score = match($trustLevel) {
            ModuleTrustRegistry::LEVEL_CORE      => 100,
            ModuleTrustRegistry::LEVEL_TRUSTED   => 85,
            ModuleTrustRegistry::LEVEL_SANDBOXED => 50,
            ModuleTrustRegistry::LEVEL_UNTRUSTED => 0,
            default                              => 0,
        };

        if (!$publisherTrusted) {
            $score = (int) round($score * 0.7);
        }

        $depPenalty = match($dependencyClassification) {
            DependencyRiskClassifier::SAFE    => 0,
            DependencyRiskClassifier::WARNING => 10,
            DependencyRiskClassifier::UNSAFE  => 30,
            DependencyRiskClassifier::BLOCKED => 100,
            default                           => 50,
        };

        $score = max(0, $score - $depPenalty);

        return new self($moduleId, $trustLevel, $publisherTrusted, $dependencyClassification, $score);
    }

    public function moduleId(): string                  { return $this->moduleId; }
    public function trustLevel(): string                { return $this->trustLevel; }
    public function publisherTrusted(): bool            { return $this->publisherTrusted; }
    public function dependencyClassification(): string  { return $this->dependencyClassification; }
    public function score(): int                        { return $this->score; }

    public function grade(): string
    {
        if ($this->score >= 90) return 'A+';
        if ($this->score >= 80) return 'A';
        if ($this->score >= 65) return 'B';
        if ($this->score >= 40) return 'C';
        if ($this->score > 0)   return 'D';
        return 'F';
    }

    public function toArray(): array
    {
        return [
            'module_id'                => $this->moduleId,
            'trust_level'              => $this->trustLevel,
            'publisher_trusted'        => $this->publisherTrusted,
            'dependency_classification'=> $this->dependencyClassification,
            'score'                    => $this->score,
            'grade'                    => $this->grade(),
        ];
    }
}
