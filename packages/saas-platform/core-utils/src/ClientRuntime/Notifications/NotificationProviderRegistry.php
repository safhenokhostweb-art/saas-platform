<?php

namespace SaaSPlatform\CoreUtils\ClientRuntime\Notifications;

use SaaSPlatform\CoreUtils\ClientRuntime\Notifications\Contracts\NotificationProviderInterface;

class NotificationProviderRegistry
{
    /** @var array<string, NotificationProviderInterface> */
    private array $providers = [];

    public function register(NotificationProviderInterface $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    public function get(string $name): ?NotificationProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * Returns all providers sorted ascending by priority (1 = highest).
     *
     * @return NotificationProviderInterface[]
     */
    public function getOrderedProviders(): array
    {
        $sorted = $this->providers;
        usort($sorted, fn ($a, $b) => $a->getPriority() <=> $b->getPriority());
        return $sorted;
    }

    /**
     * Returns available providers for a specific platform, sorted by priority.
     *
     * @return NotificationProviderInterface[]
     */
    public function getAvailableForPlatform(string $platform): array
    {
        return array_values(array_filter(
            $this->getOrderedProviders(),
            fn ($p) => $p->isAvailable() && $p->canDeliver($platform),
        ));
    }

    /** @return array<string, array> */
    public function getHealthReport(): array
    {
        $report = [];
        foreach ($this->getOrderedProviders() as $provider) {
            $report[$provider->getName()] = $provider->getHealthStatus();
        }
        return $report;
    }

    public function all(): array
    {
        return $this->providers;
    }
}
