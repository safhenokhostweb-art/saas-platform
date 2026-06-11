<?php
namespace App\Services\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Exports telemetry_events to an OpenTelemetry collector (Jaeger/Tempo) via OTLP/HTTP JSON.
 * Requires OTEL_EXPORTER_OTLP_ENDPOINT env var.
 *
 * OTLP JSON format: https://opentelemetry.io/docs/specs/otlp/
 * No SDK dependency — uses raw HTTP POST with the OTLP JSON envelope.
 *
 * Usage: php artisan telemetry:export-otlp [--from=2026-06-01] [--limit=500] [--dry-run]
 */
class OtelExportService
{
    private ?string $endpoint;
    private string  $serviceName;

    public function __construct()
    {
        $this->endpoint    = config('services.otel.endpoint');
        $this->serviceName = config('app.name', 'saas-platform');
    }

    public function isEnabled(): bool
    {
        return !empty($this->endpoint);
    }

    /**
     * Export a batch of telemetry_events as OTLP traces.
     * Returns count of events shipped.
     */
    public function exportBatch(array $events): int
    {
        if (empty($events) || !$this->isEnabled()) {
            return 0;
        }

        $payload = $this->buildOtlpPayload($events);

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpoint . '/v1/traces', $payload);

            if (!$response->successful()) {
                Log::warning('OtelExportService: OTLP endpoint rejected batch', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return 0;
            }
        } catch (\Throwable $e) {
            Log::warning('OtelExportService: export failed', ['error' => $e->getMessage()]);
            return 0;
        }

        return count($events);
    }

    private function buildOtlpPayload(array $events): array
    {
        $spans = array_map(fn ($e) => $this->eventToSpan($e), $events);

        return [
            'resourceSpans' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name',        'value' => ['stringValue' => $this->serviceName]],
                            ['key' => 'deployment.environment', 'value' => ['stringValue' => config('app.env', 'production')]],
                        ],
                    ],
                    'scopeSpans' => [
                        [
                            'scope'  => ['name' => 'saas-platform.telemetry', 'version' => '1.0'],
                            'spans'  => $spans,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function eventToSpan(object $event): array
    {
        $traceId = $this->toHex($event->trace_id ?? $event->event_uuid, 32);
        $spanId  = $this->toHex($event->event_uuid, 16);

        $startNs = strtotime((string) $event->timestamp) * 1_000_000_000;
        $endNs   = $startNs + 1_000_000; // 1ms default span duration

        $severity = strtolower($event->severity ?? 'info');
        $status   = in_array($severity, ['critical', 'error']) ? 2 : 1; // 2=ERROR, 1=OK

        $attributes = [
            ['key' => 'event.name',   'value' => ['stringValue' => (string) ($event->event ?? '')]],
            ['key' => 'event.module', 'value' => ['stringValue' => (string) ($event->module ?? '')]],
            ['key' => 'event.severity', 'value' => ['stringValue' => $severity]],
        ];

        if (!empty($event->tenant_id)) {
            $attributes[] = ['key' => 'tenant.id', 'value' => ['intValue' => (int) $event->tenant_id]];
        }

        if (!empty($event->request_id)) {
            $attributes[] = ['key' => 'http.request_id', 'value' => ['stringValue' => $event->request_id]];
        }

        $data = is_string($event->data ?? null) ? json_decode($event->data, true) : ($event->data ?? []);
        if (is_array($data)) {
            foreach (array_slice($data, 0, 10) as $k => $v) {
                $attributes[] = ['key' => "data.{$k}", 'value' => ['stringValue' => (string) $v]];
            }
        }

        return [
            'traceId'           => $traceId,
            'spanId'            => $spanId,
            'name'              => (string) ($event->event ?? 'unknown'),
            'kind'              => 2, // SERVER
            'startTimeUnixNano' => (string) $startNs,
            'endTimeUnixNano'   => (string) $endNs,
            'attributes'        => $attributes,
            'status'            => ['code' => $status],
        ];
    }

    private function toHex(?string $uuid, int $length): string
    {
        $hex = str_replace('-', '', $uuid ?? uniqid('', true));
        $hex = preg_replace('/[^0-9a-fA-F]/', '0', $hex);
        return substr(str_pad($hex, $length, '0'), 0, $length);
    }
}
