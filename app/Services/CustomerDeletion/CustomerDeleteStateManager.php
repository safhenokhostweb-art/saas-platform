<?php

namespace App\Services\CustomerDeletion;

use App\Models\CustomerDeletion;
use App\Models\CustomerDeletionLog;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class CustomerDeleteStateManager
{
    public function markRunning(CustomerDeletion $deletion): void
    {
        $deletion->update([
            'status'     => 'running',
            'started_at' => now(),
        ]);

        $this->log($deletion, 'INIT', 'started', 'حذف مشتری شروع شد');
        $this->emitProgress($deletion->id, 'INIT', 'running', 'شروع فرآیند حذف');
    }

    public function recordStageStart(CustomerDeletion $deletion, string $stage): void
    {
        $deletion->update(['current_stage' => $stage]);
        $this->log($deletion, $stage, 'started', "مرحله {$stage} شروع شد");
        $this->emitProgress($deletion->id, $stage, 'running', "در حال اجرا: {$stage}");
    }

    public function recordStageComplete(CustomerDeletion $deletion, string $stage, DeleteStageResult $result): void
    {
        $deletion->markStageComplete($stage);

        $event = $result->manual ? 'manual' : ($result->skipped ? 'skipped' : 'completed');
        $msg   = match ($event) {
            'manual'  => "مرحله {$stage} نیاز به اقدام دستی دارد: " . ($result->output['manual_action'] ?? ''),
            'skipped' => "مرحله {$stage} رد شد: " . ($result->output['reason'] ?? ''),
            default   => "مرحله {$stage} کامل شد در {$result->durationMs}ms",
        };

        $this->log($deletion, $stage, $event, $msg, $result->output, $result->durationMs);
        $this->emitProgress($deletion->id, $stage, $event, $msg);
    }

    public function recordStageFailure(CustomerDeletion $deletion, string $stage, string $error, int $durationMs): void
    {
        $error = $this->sanitizeMessage($error);
        $this->log($deletion, $stage, 'failed', $error, [], $durationMs);
        $this->emitProgress($deletion->id, $stage, 'failed', "خطا در {$stage}: {$error}");
    }

    public function markComplete(CustomerDeletion $deletion): void
    {
        $deletion->update([
            'status'       => 'complete',
            'completed_at' => now(),
        ]);
        $this->log($deletion, 'COMPLETE', 'completed', 'حذف مشتری با موفقیت انجام شد');
        $this->emitProgress($deletion->id, 'COMPLETE', 'completed', 'حذف کامل شد');
    }

    /** ADR-090 — finished, but some resources were left for a human on purpose. */
    public function markCompleteWithWarnings(CustomerDeletion $deletion, array $manualActions): void
    {
        $deletion->update([
            'status'         => CustomerDeletion::STATUS_COMPLETE_WITH_WARNINGS,
            'completed_at'   => now(),
            'manual_actions' => $manualActions,
        ]);
        $this->log($deletion, 'COMPLETE', 'completed', 'حذف انجام شد؛ ' . count($manualActions) . ' مورد نیاز به اقدام دستی دارد', ['manual_actions' => $manualActions]);
        $this->emitProgress($deletion->id, 'COMPLETE', 'completed', 'حذف انجام شد — موارد دستی باقی است');
    }

    public function markFailed(CustomerDeletion $deletion, string $reason): void
    {
        $reason = $this->sanitizeMessage($reason);
        $deletion->update([
            'status'         => 'failed',
            'failed_at'      => now(),
            'failure_reason' => $reason,
        ]);
        $this->log($deletion, 'FAILED', 'failed', $reason);
        $this->emitProgress($deletion->id, 'FAILED', 'failed', "حذف ناموفق: {$reason}");
    }

    /**
     * Phase 14-G3 — same defense-in-depth redaction InstallationStateManager
     * gained in Phase 14-G1: failure messages are free-form strings built
     * from shell/SQL command results, so any credential-shaped fragment is
     * stripped before it reaches customer_deletion_logs.message or the
     * admin-visible failure_reason. No deletion stage interpolates a secret
     * today (FinalBackupStage passes the DB password via the connection's
     * env mechanism, never the command string) — this is the second layer.
     */
    private function sanitizeMessage(string $message): string
    {
        $patterns = [
            "/IDENTIFIED BY\s+'[^']*'/i"  => "IDENTIFIED BY '[REDACTED]'",
            '/IDENTIFIED BY\s+"[^"]*"/i'  => 'IDENTIFIED BY "[REDACTED]"',
            '/MYSQL_PWD=\S+/i'             => 'MYSQL_PWD=[REDACTED]',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $message);
    }

    private function log(
        CustomerDeletion $deletion,
        string $stage,
        string $event,
        string $message,
        array $output = [],
        ?int $durationMs = null,
    ): void {
        CustomerDeletionLog::create([
            'deletion_id' => $deletion->id,
            'stage'       => $stage,
            'event'       => $event,
            'message'     => $message,
            'output'      => !empty($output) ? $output : null,
            'duration_ms' => $durationMs,
            'created_at'  => now(),
        ]);
    }

    private function emitProgress(int $deletionId, string $stage, string $status, string $message): void
    {
        try {
            $payload = json_encode([
                'deletion_id' => $deletionId,
                'stage'       => $stage,
                'status'      => $status,
                'message'     => $message,
                'timestamp'   => now()->toIso8601String(),
            ]);
            Redis::rpush("customer_deletion_progress:{$deletionId}", $payload);
            Redis::expire("customer_deletion_progress:{$deletionId}", 3600);
        } catch (\Throwable $e) {
            Log::debug("CDE: Redis progress emit failed: {$e->getMessage()}");
        }
    }
}
