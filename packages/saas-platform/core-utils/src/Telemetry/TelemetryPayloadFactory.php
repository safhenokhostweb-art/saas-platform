<?php

namespace SaaSPlatform\CoreUtils\Telemetry;

use Illuminate\Support\Str;

class TelemetryPayloadFactory
{
    public function __construct(
        private TelemetryContext   $context,
        private TelemetrySanitizer $sanitizer
    ) {}

    public function build(
        string $module,
        string $severity,
        string $event,
        array  $data = []
    ): array {
        return [
            'event_uuid'      => (string) Str::uuid(),
            'trace_id'        => $this->context->getTraceId(),
            'request_id'      => $this->context->getRequestId(),
            'tenant_id'       => $this->context->getTenantId(),
            'module'          => $module,
            'environment'     => config('app.env', 'production'),
            'release_id'      => $this->context->getReleaseId(),
            'runtime_context' => $this->buildRuntimeContext(),
            'timestamp'       => now()->toIso8601String(),
            'severity'        => $severity,
            'event'           => $event,
            'data'            => $this->sanitizer->sanitize($data),
        ];
    }

    public function buildRuntimeContext(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_mb'   => round(memory_get_usage(true) / 1024 / 1024, 2),
            'hostname'    => gethostname() ?: 'unknown',
            'pid'         => getmypid(),
            'sapi'        => PHP_SAPI,
        ];
    }
}
