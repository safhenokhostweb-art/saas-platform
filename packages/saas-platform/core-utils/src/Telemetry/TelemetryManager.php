<?php

namespace SaaSPlatform\CoreUtils\Telemetry;

class TelemetryManager
{
    public const SEVERITY_DEBUG    = 'debug';
    public const SEVERITY_INFO     = 'info';
    public const SEVERITY_WARNING  = 'warning';
    public const SEVERITY_ERROR    = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    public function __construct(
        private TelemetryContext        $context,
        private TelemetryPayloadFactory $factory,
        private TelemetryEventBus       $bus,
        private TelemetryStorageManager $storage,
        private TelemetrySanitizer      $sanitizer
    ) {}

    public function emit(string $module, string $severity, string $event, array $data = []): void
    {
        $payload = $this->factory->build($module, $severity, $event, $data);
        $this->storage->store($payload);
        $this->bus->dispatch($payload);
    }

    public function debug(string $module, string $event, array $data = []): void
    {
        $this->emit($module, self::SEVERITY_DEBUG, $event, $data);
    }

    public function info(string $module, string $event, array $data = []): void
    {
        $this->emit($module, self::SEVERITY_INFO, $event, $data);
    }

    public function warning(string $module, string $event, array $data = []): void
    {
        $this->emit($module, self::SEVERITY_WARNING, $event, $data);
    }

    public function error(string $module, string $event, array $data = []): void
    {
        $this->emit($module, self::SEVERITY_ERROR, $event, $data);
    }

    public function critical(string $module, string $event, array $data = []): void
    {
        $this->emit($module, self::SEVERITY_CRITICAL, $event, $data);
    }

    public function context(): TelemetryContext
    {
        return $this->context;
    }

    public function sanitizer(): TelemetrySanitizer
    {
        return $this->sanitizer;
    }

    public function subscribe(string $channel, callable $handler): void
    {
        $this->bus->subscribe($channel, $handler);
    }

    public function query(array $filters = [], int $limit = 100): array
    {
        return $this->storage->query($filters, $limit);
    }
}
