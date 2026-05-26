<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class CircuitHealthTracker
{
    /** @var array<string, RuntimeCircuit> */
    private array $tracked = [];

    public function __construct(private TelemetryManager $telemetry) {}

    public function track(RuntimeCircuit $circuit, string $key): void
    {
        $this->tracked[$key] = $circuit;
    }

    public function allStates(): array
    {
        return array_map(fn($circuit) => $circuit->toArray(), $this->tracked);
    }

    public function openCircuits(): array
    {
        return array_filter(
            $this->tracked,
            fn($c) => $c->state() === RuntimeCircuit::STATE_OPEN
        );
    }

    public function hasOpenCircuits(): bool
    {
        return !empty($this->openCircuits());
    }

    public function healthScore(): int
    {
        if (empty($this->tracked)) {
            return 100;
        }

        $openCount     = count($this->openCircuits());
        $halfOpenCount = count(array_filter($this->tracked, fn($c) => $c->state() === RuntimeCircuit::STATE_HALF_OPEN));
        $total         = count($this->tracked);

        $score = 100 - ($openCount / $total * 60) - ($halfOpenCount / $total * 20);
        return max(0, (int) round($score));
    }

    public function emitHealthSnapshot(): void
    {
        $openCircuits = array_keys($this->openCircuits());

        if (!empty($openCircuits)) {
            $this->telemetry->warning('governance', 'governance.circuit.health_snapshot', [
                'open_count'    => count($openCircuits),
                'open_circuits' => $openCircuits,
                'health_score'  => $this->healthScore(),
            ]);
        }
    }
}
