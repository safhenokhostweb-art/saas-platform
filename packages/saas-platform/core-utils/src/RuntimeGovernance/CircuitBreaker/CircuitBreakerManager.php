<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class CircuitBreakerManager
{
    /** @var array<string, RuntimeCircuit> */
    private array $circuits = [];

    private const CIRCUIT_CONFIGS = [
        'external_api'           => ['failures' => 5,  'recovery' => 60],
        'queue_backend'          => ['failures' => 3,  'recovery' => 30],
        'notification_channel'   => ['failures' => 3,  'recovery' => 120],
        'license_validation'     => ['failures' => 5,  'recovery' => 60],
        'cache_backend'          => ['failures' => 3,  'recovery' => 15],
    ];

    public function __construct(
        private CircuitHealthTracker $healthTracker,
        private TelemetryManager     $telemetry
    ) {}

    public function circuit(string $key): RuntimeCircuit
    {
        if (!isset($this->circuits[$key])) {
            $config = self::CIRCUIT_CONFIGS[$key] ?? ['failures' => 5, 'recovery' => 60];

            $this->circuits[$key] = new RuntimeCircuit(
                $key,
                $config['failures'],
                $config['recovery']
            );

            $this->healthTracker->track($this->circuits[$key], $key);
        }

        return $this->circuits[$key];
    }

    public function allStates(): array
    {
        return $this->healthTracker->allStates();
    }

    public function openCircuits(): array
    {
        return array_keys($this->healthTracker->openCircuits());
    }

    public function hasOpenCircuits(): bool
    {
        return $this->healthTracker->hasOpenCircuits();
    }

    public function resetAll(): void
    {
        foreach ($this->circuits as $circuit) {
            $circuit->reset();
        }

        $this->telemetry->info('governance', 'governance.circuit.all_reset', [
            'circuit_count' => count($this->circuits),
        ]);
    }

    public function reset(string $key): void
    {
        if (isset($this->circuits[$key])) {
            $this->circuits[$key]->reset();
            $this->telemetry->info('governance', 'governance.circuit.reset', ['circuit_key' => $key]);
        }
    }

    public function forceOpen(string $key): void
    {
        $this->circuit($key)->forceOpen();
        $this->telemetry->warning('governance', 'governance.circuit.force_opened', ['circuit_key' => $key]);
    }

    public function healthScore(): int
    {
        return $this->healthTracker->healthScore();
    }
}
