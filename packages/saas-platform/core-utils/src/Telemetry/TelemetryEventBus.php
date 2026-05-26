<?php

namespace SaaSPlatform\CoreUtils\Telemetry;

class TelemetryEventBus
{
    /** @var array<string, callable[]> */
    private array $handlers = [];

    public function subscribe(string $channel, callable $handler): void
    {
        $this->handlers[$channel][] = $handler;
    }

    public function dispatch(array $payload): void
    {
        // Broadcast to wildcard handlers
        $this->dispatchToChannel('*', $payload);

        // Module-specific handlers
        $module = $payload['module'] ?? 'unknown';
        if ($module !== '*') {
            $this->dispatchToChannel($module, $payload);
        }

        // Severity-specific handlers
        $severity = $payload['severity'] ?? 'info';
        $this->dispatchToChannel('severity:' . $severity, $payload);
    }

    public function dispatchToChannel(string $channel, array $payload): void
    {
        foreach ($this->handlers[$channel] ?? [] as $handler) {
            try {
                $handler($payload);
            } catch (\Throwable) {
                // Bus failures must not propagate into application flow
            }
        }
    }

    public function hasHandlers(string $channel): bool
    {
        return !empty($this->handlers[$channel]);
    }

    public function registeredChannels(): array
    {
        return array_keys($this->handlers);
    }
}
