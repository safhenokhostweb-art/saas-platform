<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Tracing;

class RuntimeRequestProfiler
{
    private float $startTime;
    private int   $startMemory;
    private array $checkpoints = [];
    private bool  $started     = false;

    public function start(): void
    {
        $this->startTime   = microtime(true);
        $this->startMemory = memory_get_usage(true);
        $this->checkpoints = [];
        $this->started     = true;
    }

    public function checkpoint(string $label): void
    {
        if (!$this->started) {
            return;
        }

        $this->checkpoints[] = [
            'label'      => $label,
            'elapsed_ms' => $this->elapsedMs(),
            'memory_mb'  => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];
    }

    public function finish(): array
    {
        return [
            'elapsed_ms'      => $this->elapsedMs(),
            'peak_memory_mb'  => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'start_memory_mb' => round(($this->startMemory ?? 0) / 1024 / 1024, 2),
            'checkpoints'     => $this->checkpoints,
        ];
    }

    public function elapsedMs(): float
    {
        if (!$this->started) {
            return 0.0;
        }
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }

    public function isStarted(): bool
    {
        return $this->started;
    }
}
