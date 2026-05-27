<?php

namespace SaaSPlatform\CoreUtils\AI;

class AiCapabilityRegistry
{
    // Capability → required provider capability
    private const CAPABILITY_MAP = [
        'form_generation'      => ['chat', 'completion'],
        'form_optimization'    => ['chat', 'completion'],
        'submission_analysis'  => ['chat', 'completion'],
        'ticket_reply'         => ['chat', 'completion'],
        'ticket_categorize'    => ['chat', 'classification'],
        'ticket_sla_risk'      => ['chat', 'completion'],
        'summarization'        => ['summarization', 'chat'],
        'embeddings'           => ['embeddings'],
        'json_structured'      => ['json_mode', 'chat'],
        'sentiment_analysis'   => ['chat', 'classification'],
        'spam_detection'       => ['chat', 'classification'],
    ];

    public function __construct(
        private readonly AiProviderManager $manager
    ) {}

    public function canExecute(string $capability): bool
    {
        if (! isset(self::CAPABILITY_MAP[$capability])) {
            return $this->manager->isAnyAvailable();
        }

        $required = self::CAPABILITY_MAP[$capability];
        $provider = $this->manager->getActive();

        if (! $provider) return false;

        $providerCaps = $provider->getCapabilities();
        // Provider must support at least ONE of the required capabilities
        return ! empty(array_intersect($required, $providerCaps));
    }

    public function getBestProviderFor(string $capability): ?string
    {
        $required = self::CAPABILITY_MAP[$capability] ?? ['chat'];

        foreach ($this->manager->all() as $name => $provider) {
            if (! $provider->isAvailable() || $this->manager->isCircuitOpen($name)) continue;
            if (! empty(array_intersect($required, $provider->getCapabilities()))) {
                return $name;
            }
        }

        return null;
    }

    public function getAvailableCapabilities(): array
    {
        $provider = $this->manager->getActive();
        if (! $provider) return [];

        $providerCaps = $provider->getCapabilities();
        $available    = [];

        foreach (self::CAPABILITY_MAP as $cap => $required) {
            if (! empty(array_intersect($required, $providerCaps))) {
                $available[] = $cap;
            }
        }

        return $available;
    }

    public function getCapabilityMap(): array
    {
        return self::CAPABILITY_MAP;
    }
}
