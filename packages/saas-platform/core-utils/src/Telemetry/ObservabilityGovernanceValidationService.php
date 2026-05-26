<?php

namespace SaaSPlatform\CoreUtils\Telemetry;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ObservabilityGovernanceValidationService
{
    private array $checks  = [];
    private array $scores  = [];
    private array $warnings = [];
    private array $failures = [];

    public function validate(): array
    {
        $this->checks  = [];
        $this->scores  = [];
        $this->warnings = [];
        $this->failures = [];

        $this->checkTelemetryCore();
        $this->checkDistributedTracing();
        $this->checkStructuredLogging();
        $this->checkAnomalyDetection();
        $this->checkTenantObservability();
        $this->checkSecurityTelemetry();
        $this->checkStorageAvailability();
        $this->checkSanitization();
        $this->checkIncidentReconstruction();

        $overall = count($this->scores) > 0
            ? (int) round(array_sum($this->scores) / count($this->scores))
            : 0;

        return [
            'overall_score' => $overall,
            'grade'         => $this->grade($overall),
            'dimensions'    => $this->scores,
            'checks'        => $this->checks,
            'warnings'      => $this->warnings,
            'failures'      => $this->failures,
            'validated_at'  => now()->toIso8601String(),
        ];
    }

    private function checkTelemetryCore(): void
    {
        $score = 0;

        if (app()->bound(TelemetryManager::class)) {
            $score += 40;
            $this->pass('telemetry_core', 'TelemetryManager bound in container');
        } else {
            $this->fail('telemetry_core', 'TelemetryManager not bound in container');
        }

        if (app()->bound(TelemetryContext::class)) {
            $score += 30;
            $this->pass('telemetry_context', 'TelemetryContext bound in container');
        } else {
            $this->fail('telemetry_context', 'TelemetryContext not bound');
        }

        if (app()->bound(TelemetrySanitizer::class)) {
            $score += 30;
            $this->pass('telemetry_sanitizer', 'TelemetrySanitizer bound — sensitive data protection active');
        } else {
            $this->fail('telemetry_sanitizer', 'TelemetrySanitizer not bound — sensitive data at risk');
        }

        $this->scores['telemetry_core'] = $score;
    }

    private function checkDistributedTracing(): void
    {
        $score = 0;

        if (app()->bound(\SaaSPlatform\CoreUtils\Telemetry\Tracing\RequestTraceManager::class)) {
            $score += 50;
            $this->pass('tracing_manager', 'RequestTraceManager bound');
        } else {
            $this->warn('tracing_manager', 'RequestTraceManager not bound — distributed tracing inactive');
        }

        if (class_exists(\App\Http\Middleware\CorrelationIdMiddleware::class)) {
            $score += 50;
            $this->pass('correlation_middleware', 'CorrelationIdMiddleware exists');
        } else {
            $this->warn('correlation_middleware', 'CorrelationIdMiddleware not found');
        }

        $this->scores['distributed_tracing'] = $score;
    }

    private function checkStructuredLogging(): void
    {
        $score = 0;

        $loggers = [
            \SaaSPlatform\CoreUtils\Telemetry\Logging\StructuredLogger::class          => 'StructuredLogger',
            \SaaSPlatform\CoreUtils\Telemetry\Logging\SecurityEventLogger::class       => 'SecurityEventLogger',
            \SaaSPlatform\CoreUtils\Telemetry\Logging\DeploymentEventLogger::class     => 'DeploymentEventLogger',
        ];

        foreach ($loggers as $class => $label) {
            if (app()->bound($class)) {
                $score += 33;
                $this->pass('logger_' . strtolower($label), "{$label} bound");
            } else {
                $this->warn('logger_' . strtolower($label), "{$label} not bound");
            }
        }

        $this->scores['structured_logging'] = min(100, $score);
    }

    private function checkAnomalyDetection(): void
    {
        $score = app()->bound(\SaaSPlatform\CoreUtils\Telemetry\Anomaly\RuntimeAnomalyEngine::class) ? 100 : 0;

        if ($score > 0) {
            $this->pass('anomaly_engine', 'RuntimeAnomalyEngine bound');
        } else {
            $this->warn('anomaly_engine', 'RuntimeAnomalyEngine not bound');
        }

        $this->scores['anomaly_detection'] = $score;
    }

    private function checkTenantObservability(): void
    {
        $score = 0;

        if (app()->bound(\SaaSPlatform\CoreUtils\Telemetry\Tenant\TenantObservabilityService::class)) {
            $score += 50;
            $this->pass('tenant_observability', 'TenantObservabilityService bound');
        } else {
            $this->warn('tenant_observability', 'TenantObservabilityService not bound');
        }

        if (app()->bound(\SaaSPlatform\CoreUtils\Telemetry\Tenant\TenantRuntimeMetrics::class)) {
            $score += 50;
            $this->pass('tenant_metrics', 'TenantRuntimeMetrics bound');
        } else {
            $this->warn('tenant_metrics', 'TenantRuntimeMetrics not bound');
        }

        $this->scores['tenant_observability'] = $score;
    }

    private function checkSecurityTelemetry(): void
    {
        $score = 0;

        if (app()->bound(\SaaSPlatform\CoreUtils\Telemetry\Security\SecurityTelemetryService::class)) {
            $score += 60;
            $this->pass('security_telemetry', 'SecurityTelemetryService bound');
        } else {
            $this->fail('security_telemetry', 'SecurityTelemetryService not bound — security events not tracked');
        }

        if (app()->bound(\SaaSPlatform\CoreUtils\Telemetry\Security\TenantBoundaryViolationTelemetry::class)) {
            $score += 40;
            $this->pass('boundary_telemetry', 'TenantBoundaryViolationTelemetry bound');
        } else {
            $this->fail('boundary_telemetry', 'Tenant boundary violations not tracked');
        }

        $this->scores['security_telemetry'] = $score;
    }

    private function checkStorageAvailability(): void
    {
        $score = 60; // Log storage always available

        try {
            $hasTable = DB::getSchemaBuilder()->hasTable('telemetry_events');
            if ($hasTable) {
                $score = 100;
                $this->pass('telemetry_storage_db', 'telemetry_events table exists — DB storage active');
            } else {
                $this->warn('telemetry_storage_db', 'telemetry_events table missing — log-only storage (run migrations)');
            }
        } catch (\Throwable) {
            $this->warn('telemetry_storage_db', 'Cannot verify telemetry_events table');
        }

        $this->scores['storage_availability'] = $score;
    }

    private function checkSanitization(): void
    {
        // Verify the sanitizer actually redacts known sensitive keys
        $sanitizer = app(\SaaSPlatform\CoreUtils\Telemetry\TelemetrySanitizer::class);

        $test = $sanitizer->sanitize([
            'authorization' => 'Bearer abc123',
            'license_key'   => 'XXXX-YYYY-ZZZZ-AAAA',
            'safe_field'    => 'hello',
        ]);

        $score = 0;
        if ($test['authorization'] === '[REDACTED]') { $score += 50; }
        if ($test['license_key'] === '[REDACTED]')   { $score += 50; }

        $this->scores['sanitization'] = $score;

        if ($score === 100) {
            $this->pass('sanitization', 'TelemetrySanitizer correctly redacts sensitive keys');
        } else {
            $this->fail('sanitization', 'TelemetrySanitizer failed redaction test — sensitive data may leak');
        }
    }

    private function checkIncidentReconstruction(): void
    {
        $score = app()->bound(\SaaSPlatform\CoreUtils\Telemetry\Incident\IncidentReconstructionService::class) ? 100 : 0;

        if ($score > 0) {
            $this->pass('incident_reconstruction', 'IncidentReconstructionService bound');
        } else {
            $this->warn('incident_reconstruction', 'IncidentReconstructionService not bound');
        }

        $this->scores['incident_reconstruction'] = $score;
    }

    private function pass(string $id, string $message): void
    {
        $this->checks[] = ['id' => $id, 'status' => 'pass', 'message' => $message];
    }

    private function warn(string $id, string $message): void
    {
        $this->checks[]   = ['id' => $id, 'status' => 'warning', 'message' => $message];
        $this->warnings[] = $message;
    }

    private function fail(string $id, string $message): void
    {
        $this->checks[]  = ['id' => $id, 'status' => 'fail', 'message' => $message];
        $this->failures[] = $message;
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 95 => 'A+',
            $score >= 85 => 'A',
            $score >= 75 => 'B',
            $score >= 60 => 'C',
            $score >= 40 => 'D',
            default      => 'F',
        };
    }
}
