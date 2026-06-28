<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerInstallRollback;
use App\Services\CustomerInstallation\Contracts\InstallationStateInterface;
use Illuminate\Support\Facades\Redis;

class InstallationStateManager implements InstallationStateInterface
{
    public function markRunning(CustomerInstallation $installation): void
    {
        $installation->update([
            'status'     => 'running',
            'started_at' => now(),
        ]);

        CustomerInstallationLog::create([
            'installation_id' => $installation->id,
            'stage'           => 'INIT',
            'event'           => 'started',
            'message'         => 'Installation pipeline started',
            'created_at'      => now(),
        ]);

        $this->emitProgress($installation->id, 'INIT', 'running', 'نصب شروع شد');
    }

    public function recordStageStart(CustomerInstallation $installation, string $stage): void
    {
        $installation->update(['current_stage' => $stage]);

        CustomerInstallationLog::create([
            'installation_id' => $installation->id,
            'stage'           => $stage,
            'event'           => 'started',
            'message'         => "Stage {$stage} started",
            'created_at'      => now(),
        ]);

        $this->emitProgress($installation->id, $stage, 'running', "در حال اجرا: {$stage}");
    }

    public function recordStageComplete(
        CustomerInstallation $installation,
        string $stage,
        array $output,
        int $durationMs
    ): void {
        $installation->markStageComplete($stage);

        CustomerInstallationLog::create([
            'installation_id' => $installation->id,
            'stage'           => $stage,
            'event'           => 'completed',
            'message'         => "Stage {$stage} completed in {$durationMs}ms",
            'output'          => $this->sanitizeOutput($output),
            'duration_ms'     => $durationMs,
            'created_at'      => now(),
        ]);

        $this->emitProgress($installation->id, $stage, 'completed', "تکمیل شد: {$stage}");
    }

    public function recordStageFailure(
        CustomerInstallation $installation,
        string $stage,
        string $error,
        int $durationMs
    ): void {
        CustomerInstallationLog::create([
            'installation_id' => $installation->id,
            'stage'           => $stage,
            'event'           => 'failed',
            'message'         => $error,
            'duration_ms'     => $durationMs,
            'created_at'      => now(),
        ]);

        $this->emitProgress($installation->id, $stage, 'failed', "خطا در: {$stage}");
    }

    public function recordRollbackCheckpoint(
        CustomerInstallation $installation,
        string $stage,
        array $snapshot
    ): void {
        // Upsert: update the existing pending checkpoint rather than creating a duplicate.
        // The orchestrator calls this method twice per stage (pre-execute + post-execute),
        // so we must not accumulate two records — the rollback service would execute both.
        $existing = CustomerInstallRollback::where('installation_id', $installation->id)
            ->where('stage', $stage)
            ->whereNull('executed_at')
            ->first();

        $sanitized = $this->sanitizeSnapshot($snapshot);

        if ($existing) {
            $existing->update(['snapshot' => $sanitized]);
        } else {
            CustomerInstallRollback::create([
                'installation_id' => $installation->id,
                'stage'           => $stage,
                'snapshot'        => $sanitized,
                'created_at'      => now(),
            ]);

            CustomerInstallationLog::create([
                'installation_id' => $installation->id,
                'stage'           => $stage,
                'event'           => 'checkpoint',
                'message'         => "Rollback checkpoint recorded for {$stage}",
                'created_at'      => now(),
            ]);
        }
    }

    public function recordWarning(CustomerInstallation $installation, string $stage, string $message): void
    {
        CustomerInstallationLog::create([
            'installation_id' => $installation->id,
            'stage'           => $stage,
            'event'           => 'warning',
            'message'         => $message,
            'created_at'      => now(),
        ]);
    }

    public function markComplete(CustomerInstallation $installation): void
    {
        $installation->update([
            'status'       => 'complete',
            'completed_at' => now(),
        ]);

        CustomerInstallationLog::create([
            'installation_id' => $installation->id,
            'stage'           => 'CUSTOMER_READY',
            'event'           => 'completed',
            'message'         => 'Installation completed successfully',
            'created_at'      => now(),
        ]);

        $this->emitProgress($installation->id, 'CUSTOMER_READY', 'complete', 'نصب با موفقیت تکمیل شد');
    }

    public function markFailed(CustomerInstallation $installation, string $reason): void
    {
        $installation->update([
            'status'         => 'failed',
            'failed_at'      => now(),
            'failure_reason' => $reason,
        ]);

        $this->emitProgress($installation->id, $installation->current_stage ?? '', 'failed', 'نصب با خطا متوقف شد');
    }

    public function markRollingBack(CustomerInstallation $installation): void
    {
        $installation->update(['status' => 'rolling_back']);

        CustomerInstallationLog::create([
            'installation_id' => $installation->id,
            'stage'           => 'ROLLBACK',
            'event'           => 'started',
            'message'         => 'Rollback initiated',
            'created_at'      => now(),
        ]);
    }

    public function markRolledBack(CustomerInstallation $installation): void
    {
        $installation->update(['status' => 'rolled_back']);

        CustomerInstallationLog::create([
            'installation_id' => $installation->id,
            'stage'           => 'ROLLBACK',
            'event'           => 'rolled_back',
            'message'         => 'Rollback completed successfully',
            'created_at'      => now(),
        ]);
    }

    public function markRollbackFailed(CustomerInstallation $installation, string $reason): void
    {
        $installation->update([
            'status'         => 'rollback_failed',
            'failure_reason' => $installation->failure_reason . ' | Rollback also failed: ' . $reason,
        ]);
    }

    private function emitProgress(int $installationId, string $stage, string $status, string $message): void
    {
        try {
            $ttl = config('customer_installation.pipeline.redis_progress_ttl', 86400);
            $key = "install_progress_{$installationId}";
            $payload = json_encode([
                'stage'     => $stage,
                'status'    => $status,
                'message'   => $message,
                'timestamp' => now()->toIso8601String(),
            ]);
            Redis::rpush($key, $payload);
            Redis::expire($key, $ttl);
        } catch (\Throwable) {
            // Redis unavailable — non-fatal, pipeline continues
        }
    }

    private function sanitizeOutput(array $output): array
    {
        // Remove any password-looking values from output
        array_walk_recursive($output, function (&$value) {
            if (is_string($value) && preg_match('/password|secret|key|token/i', $value)) {
                $value = '[REDACTED]';
            }
        });
        return $output;
    }

    private function sanitizeSnapshot(array $snapshot): array
    {
        // Rollback snapshots must never contain passwords
        $safe = [];
        $allowedKeys = ['remote_path', 'db_name', 'db_user', 'config_path', 'cron_path', 'site_id', 'license_id'];
        foreach ($allowedKeys as $key) {
            if (isset($snapshot[$key])) {
                $safe[$key] = $snapshot[$key];
            }
        }
        return $safe;
    }
}
