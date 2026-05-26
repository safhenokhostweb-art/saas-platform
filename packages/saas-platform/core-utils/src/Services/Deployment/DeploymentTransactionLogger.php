<?php

namespace SaaSPlatform\CoreUtils\Services\Deployment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Logs every deployment step to the deployment_logs table
 * and the application log channel simultaneously.
 */
class DeploymentTransactionLogger
{
    private array $timers = [];

    public function started(string $uuid, string $phase, array $context = []): void
    {
        $this->timers[$uuid . ':' . $phase] = microtime(true);
        $this->write($uuid, $phase, 'started', null, $context);
    }

    public function completed(string $uuid, string $phase, ?string $message = null, array $context = []): void
    {
        $duration = $this->elapsed($uuid, $phase);
        $this->write($uuid, $phase, 'completed', $message, $context, $duration);
    }

    public function failed(string $uuid, string $phase, string $message, array $context = []): void
    {
        $duration = $this->elapsed($uuid, $phase);
        $this->write($uuid, $phase, 'failed', $message, $context, $duration);
        Log::error("deployment.{$phase}.failed", array_merge(['uuid' => $uuid, 'message' => $message], $context));
    }

    public function skipped(string $uuid, string $phase, string $reason): void
    {
        $this->write($uuid, $phase, 'skipped', $reason);
    }

    public function rolledBack(string $uuid, string $phase, string $reason): void
    {
        $this->write($uuid, $phase, 'rolled_back', $reason);
        Log::warning("deployment.{$phase}.rolled_back", ['uuid' => $uuid, 'reason' => $reason]);
    }

    public function getLogs(string $uuid): array
    {
        try {
            return DB::table('deployment_logs')
                ->where('deployment_uuid', $uuid)
                ->orderBy('logged_at')
                ->get()
                ->map(fn($r) => (array) $r)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function write(
        string  $uuid,
        string  $phase,
        string  $status,
        ?string $message = null,
        array   $context = [],
        ?int    $durationMs = null
    ): void {
        try {
            DB::table('deployment_logs')->insert([
                'deployment_uuid' => $uuid,
                'phase'           => $phase,
                'status'          => $status,
                'message'         => $message,
                'context'         => $context ? json_encode($context) : null,
                'duration_ms'     => $durationMs,
                'logged_at'       => now(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('deployment.logger.write_failed', ['error' => $e->getMessage()]);
        }

        Log::info("deployment.{$phase}.{$status}", array_merge(['uuid' => $uuid], $context));
    }

    private function elapsed(string $uuid, string $phase): ?int
    {
        $key = $uuid . ':' . $phase;
        if (!isset($this->timers[$key])) {
            return null;
        }
        return (int) round((microtime(true) - $this->timers[$key]) * 1000);
    }
}
