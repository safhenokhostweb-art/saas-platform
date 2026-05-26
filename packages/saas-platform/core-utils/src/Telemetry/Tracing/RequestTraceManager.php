<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Tracing;

use Illuminate\Support\Str;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;

class RequestTraceManager
{
    public function __construct(private TelemetryContext $context) {}

    public function initFromHeaders(array $headers): void
    {
        $traceId   = $this->extractHeader($headers, 'x-trace-id')   ?? (string) Str::uuid();
        $requestId = $this->extractHeader($headers, 'x-request-id') ?? (string) Str::uuid();
        $releaseId = $this->extractHeader($headers, 'x-release-id');

        $this->context->setTraceId($traceId);
        $this->context->setRequestId($requestId);

        if ($releaseId) {
            $this->context->setReleaseId($releaseId);
        }
    }

    public function initForConsole(): void
    {
        $this->context->setTraceId('cli-' . (string) Str::uuid());
        $this->context->setRequestId('cmd-' . (string) Str::uuid());
    }

    public function propagationHeaders(): array
    {
        return array_filter([
            'X-Trace-Id'   => $this->context->getTraceId(),
            'X-Request-Id' => $this->context->getRequestId(),
            'X-Release-Id' => $this->context->getReleaseId(),
        ]);
    }

    public function forQueuePayload(): array
    {
        return [
            'parent_trace_id'   => $this->context->getTraceId(),
            'parent_request_id' => $this->context->getRequestId(),
            'tenant_id'         => $this->context->getTenantId(),
            'release_id'        => $this->context->getReleaseId(),
            'enqueued_at'       => now()->toIso8601String(),
        ];
    }

    public function restoreFromQueuePayload(array $queueContext): void
    {
        if (!empty($queueContext['parent_trace_id'])) {
            $this->context->setTraceId($queueContext['parent_trace_id']);
        }
        if (!empty($queueContext['tenant_id'])) {
            $this->context->setTenantId($queueContext['tenant_id']);
        }
        if (!empty($queueContext['release_id'])) {
            $this->context->setReleaseId($queueContext['release_id']);
        }
        // Each queue job gets its own request_id
        $this->context->setRequestId('job-' . (string) Str::uuid());
    }

    private function extractHeader(array $headers, string $name): ?string
    {
        $normalized = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $normalized) {
                return is_array($value) ? ($value[0] ?? null) : (string) $value;
            }
        }

        return null;
    }
}
