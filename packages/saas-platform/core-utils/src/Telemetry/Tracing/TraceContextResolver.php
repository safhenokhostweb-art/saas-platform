<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Tracing;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;

class TraceContextResolver
{
    public function __construct(private TelemetryContext $context) {}

    public function resolve(): array
    {
        return [
            'trace_id'   => $this->context->getTraceId(),
            'request_id' => $this->context->getRequestId(),
            'tenant_id'  => $this->context->getTenantId(),
            'release_id' => $this->context->getReleaseId(),
            'module'     => $this->context->getModule(),
        ];
    }

    public function resolveForLog(): array
    {
        return array_filter($this->resolve(), fn($v) => $v !== null);
    }

    public function isTraced(): bool
    {
        return $this->context->getTraceId() !== null;
    }

    public function hasDeploymentContext(): bool
    {
        return $this->context->getReleaseId() !== null;
    }

    public function hasTenantContext(): bool
    {
        return $this->context->getTenantId() !== null;
    }
}
