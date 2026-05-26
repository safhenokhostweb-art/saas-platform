<?php

namespace SaaSPlatform\CoreUtils\Telemetry;

class TelemetryContext
{
    private ?string $traceId    = null;
    private ?string $requestId  = null;
    private ?int    $tenantId   = null;
    private string  $module     = 'core';
    private ?string $releaseId  = null;
    private array   $baggage    = [];

    public function setTraceId(string $traceId): void   { $this->traceId   = $traceId; }
    public function setRequestId(string $requestId): void { $this->requestId = $requestId; }
    public function setTenantId(?int $tenantId): void   { $this->tenantId  = $tenantId; }
    public function setModule(string $module): void     { $this->module    = $module; }
    public function setReleaseId(?string $id): void     { $this->releaseId = $id; }

    public function setBaggage(string $key, mixed $value): void
    {
        $this->baggage[$key] = $value;
    }

    public function getTraceId(): ?string  { return $this->traceId; }
    public function getRequestId(): ?string { return $this->requestId; }
    public function getTenantId(): ?int    { return $this->tenantId; }
    public function getModule(): string    { return $this->module; }
    public function getReleaseId(): ?string { return $this->releaseId; }

    public function getBaggage(string $key, mixed $default = null): mixed
    {
        return $this->baggage[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'trace_id'   => $this->traceId,
            'request_id' => $this->requestId,
            'tenant_id'  => $this->tenantId,
            'module'     => $this->module,
            'release_id' => $this->releaseId,
        ];
    }

    public function reset(): void
    {
        $this->traceId   = null;
        $this->requestId = null;
        $this->tenantId  = null;
        $this->module    = 'core';
        $this->releaseId = null;
        $this->baggage   = [];
    }
}
