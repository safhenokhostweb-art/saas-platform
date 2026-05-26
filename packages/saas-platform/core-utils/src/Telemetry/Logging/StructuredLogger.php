<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Logging;

use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;
use SaaSPlatform\CoreUtils\Telemetry\TelemetrySanitizer;

class StructuredLogger
{
    public function __construct(
        private TelemetryContext   $context,
        private TelemetrySanitizer $sanitizer
    ) {}

    public function log(string $level, string $message, array $context = []): void
    {
        Log::$level($message, $this->buildEntry($context));
    }

    public function info(string $message, array $context = []): void
    {
        Log::info($message, $this->buildEntry($context));
    }

    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, $this->buildEntry($context));
    }

    public function error(string $message, array $context = []): void
    {
        Log::error($message, $this->buildEntry($context));
    }

    public function critical(string $message, array $context = []): void
    {
        Log::critical($message, $this->buildEntry($context));
    }

    public function debug(string $message, array $context = []): void
    {
        Log::debug($message, $this->buildEntry($context));
    }

    private function buildEntry(array $context): array
    {
        return [
            'trace_id'    => $this->context->getTraceId(),
            'request_id'  => $this->context->getRequestId(),
            'tenant_id'   => $this->context->getTenantId(),
            'module'      => $this->context->getModule(),
            'release_id'  => $this->context->getReleaseId(),
            'environment' => config('app.env', 'production'),
            'timestamp'   => now()->toIso8601String(),
            'context'     => $this->sanitizer->sanitize($context),
        ];
    }
}
