<?php

namespace SaaSPlatform\CoreUtils\Telemetry;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelemetryStorageManager
{
    private ?bool $dbTableExists = null;

    public function store(array $payload): void
    {
        $this->writeToLog($payload);

        if ($this->isDbAvailable()) {
            $this->writeToDb($payload);
        }
    }

    public function query(array $filters = [], int $limit = 100): array
    {
        if (!$this->isDbAvailable()) {
            return [];
        }

        $q = DB::table('telemetry_events');

        if (!empty($filters['tenant_id']))  { $q->where('tenant_id', $filters['tenant_id']); }
        if (!empty($filters['module']))     { $q->where('module', $filters['module']); }
        if (!empty($filters['severity']))   { $q->where('severity', $filters['severity']); }
        if (!empty($filters['trace_id']))   { $q->where('trace_id', $filters['trace_id']); }
        if (!empty($filters['event']))      { $q->where('event', 'like', '%' . $filters['event'] . '%'); }
        if (!empty($filters['from']))       { $q->where('timestamp', '>=', $filters['from']); }
        if (!empty($filters['to']))         { $q->where('timestamp', '<=', $filters['to']); }

        return $q->orderByDesc('created_at')->limit($limit)->get()->map(fn($r) => (array) $r)->toArray();
    }

    public function countBySeverity(array $filters = []): array
    {
        if (!$this->isDbAvailable()) {
            return [];
        }

        $q = DB::table('telemetry_events');
        if (!empty($filters['tenant_id'])) { $q->where('tenant_id', $filters['tenant_id']); }
        if (!empty($filters['from']))      { $q->where('created_at', '>=', $filters['from']); }

        return $q->selectRaw('severity, count(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity')
            ->toArray();
    }

    private function writeToLog(array $payload): void
    {
        $severity = strtolower($payload['severity'] ?? 'info');
        $method   = match ($severity) {
            'critical', 'emergency' => 'critical',
            'error'                 => 'error',
            'warning'               => 'warning',
            'debug'                 => 'debug',
            default                 => 'info',
        };

        Log::channel(config('saas-platform.telemetry.log_channel', 'daily'))
            ->$method('telemetry.' . ($payload['event'] ?? 'event'), $payload);
    }

    private function writeToDb(array $payload): void
    {
        try {
            DB::table('telemetry_events')->insert([
                'event_uuid'      => $payload['event_uuid'],
                'trace_id'        => $payload['trace_id'],
                'request_id'      => $payload['request_id'],
                'tenant_id'       => $payload['tenant_id'],
                'module'          => $payload['module'] ?? 'unknown',
                'environment'     => $payload['environment'] ?? config('app.env'),
                'release_id'      => $payload['release_id'],
                'severity'        => $payload['severity'],
                'event'           => $payload['event'],
                'runtime_context' => json_encode($payload['runtime_context'] ?? []),
                'data'            => json_encode($payload['data'] ?? []),
                'timestamp'       => $payload['timestamp'],
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable) {
            // DB write failure must not disrupt application
        }
    }

    private function isDbAvailable(): bool
    {
        if ($this->dbTableExists !== null) {
            return $this->dbTableExists;
        }

        try {
            $this->dbTableExists = DB::getSchemaBuilder()->hasTable('telemetry_events');
        } catch (\Throwable) {
            $this->dbTableExists = false;
        }

        return $this->dbTableExists;
    }
}
