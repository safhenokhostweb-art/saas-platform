<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RestorePlatformDatabaseJob;
use App\Jobs\RestorePlatformStorageJob;
use App\Jobs\RestoreTenantDatabaseJob;
use App\Models\Infrastructure\RestoreLog;
use App\Models\Site;
use App\Services\Infrastructure\RestoreService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RestoreWizardController extends Controller
{
    public function __construct(private RestoreService $restore) {}

    // ─── Main Wizard View ─────────────────────────────────────────────────────

    public function index()
    {
        $sites        = Site::orderBy('title')->get(['id', 'title', 'domain']);
        $recentLogs   = RestoreLog::with('site', 'executor')
            ->latest()
            ->limit(10)
            ->get();
        $lockActive   = $this->restore->isLocked();
        $lockValue    = $lockActive ? $this->restore->getLockValue() : null;

        return view('admin.disaster-recovery.index', compact('sites', 'recentLogs', 'lockActive', 'lockValue'));
    }

    // ─── AJAX: List Backups ───────────────────────────────────────────────────

    public function browse(Request $request)
    {
        $type   = $request->query('type');
        $siteId = $request->query('site_id') ? (int) $request->query('site_id') : null;

        $allowedTypes = [
            RestoreLog::TYPE_PLATFORM_DATABASE,
            RestoreLog::TYPE_PLATFORM_STORAGE,
            RestoreLog::TYPE_TENANT_DATABASE,
            RestoreLog::TYPE_TENANT_STORAGE,
        ];

        if (!in_array($type, $allowedTypes, true)) {
            return response()->json(['error' => 'نوع بازیابی نامعتبر است'], 422);
        }

        if (str_starts_with($type, 'tenant') && !$siteId) {
            return response()->json(['error' => 'site_id برای بازیابی تِنِنت الزامی است'], 422);
        }

        $files = $this->restore->listBackups($type, $siteId);

        return response()->json([
            'files'  => $files,
            'prefix' => $this->restore->remotePrefixFor($type, $siteId),
            'count'  => count($files),
        ]);
    }

    // ─── AJAX: Validate a backup ──────────────────────────────────────────────

    public function checkBackup(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|string|max:500',
            'type'        => 'required|string',
            'site_id'     => 'nullable|integer|exists:sites,id',
        ]);

        $type   = $request->input('type');
        $file   = $request->input('backup_file');
        $siteId = $request->input('site_id') ? (int) $request->input('site_id') : null;
        $result = $this->restore->validateBackup($file, $type, $siteId);

        return response()->json($result);
    }

    // ─── POST: Dry Run ────────────────────────────────────────────────────────

    public function dryRun(Request $request)
    {
        $request->validate([
            'type'        => 'required|string',
            'backup_file' => 'required|string|max:500',
            'site_id'     => 'nullable|integer|exists:sites,id',
        ]);

        $type   = $request->input('type');
        $file   = $request->input('backup_file');
        $siteId = $request->input('site_id') ? (int) $request->input('site_id') : null;

        // Cross-tenant protection: tenant type must have a site_id
        if (str_starts_with($type, 'tenant') && !$siteId) {
            return response()->json(['error' => 'site_id برای بازیابی تِنِنت الزامی است'], 422);
        }

        // Verify tenant isolation: if restoring tenant, ensure site exists
        if ($siteId && !Site::find($siteId)) {
            return response()->json(['error' => 'سایت مشتری یافت نشد'], 404);
        }

        $result = $this->restore->dryRun($type, $file, $siteId);

        // Log dry run
        RestoreLog::create([
            'restore_type'      => $type,
            'site_id'           => $siteId,
            'backup_file'       => $file,
            'status'            => RestoreLog::STATUS_DRY_RUN_COMPLETE,
            'dry_run'           => true,
            'validation_result' => $result['validation'],
            'executed_by'       => auth()->id(),
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        return response()->json($result);
    }

    // ─── POST: Execute Restore ────────────────────────────────────────────────

    public function execute(Request $request)
    {
        $request->validate([
            'type'             => 'required|string',
            'backup_file'      => 'required|string|max:500',
            'site_id'          => 'nullable|integer|exists:sites,id',
            'merge_strategy'   => 'nullable|in:overwrite,merge',
            'confirmation'     => 'required|accepted',
        ]);

        $type          = $request->input('type');
        $backupFile    = $request->input('backup_file');
        $siteId        = $request->input('site_id') ? (int) $request->input('site_id') : null;
        $mergeStrategy = $request->input('merge_strategy', 'merge');

        if ($type === RestoreLog::TYPE_TENANT_STORAGE) {
            return response()->json([
                'error' => 'بازیابی storage تِنِنت تا تعریف فرمت آرشیو tenant-scoped غیرفعال است؛ بازیابی platform storage استفاده نشد.',
            ], 422);
        }

        // ── Safety checks ──────────────────────────────────────────────────────

        // Block concurrent restores
        if ($this->restore->isLocked()) {
            return response()->json([
                'error' => 'یک بازیابی در حال اجرا است: ' . $this->restore->getLockValue(),
            ], 409);
        }

        // Tenant isolation: never allow wrong type+site combo
        if (str_starts_with($type, 'tenant') && !$siteId) {
            return response()->json(['error' => 'site_id برای بازیابی تِنِنت الزامی است'], 422);
        }

        // Additional platform DB confirmation
        if ($type === RestoreLog::TYPE_PLATFORM_DATABASE) {
            $confirmText = $request->input('confirm_text', '');
            if ($confirmText !== 'RESTORE-PLATFORM') {
                return response()->json(['error' => 'متن تأییدیه اشتباه است. باید دقیقاً RESTORE-PLATFORM باشد'], 422);
            }
        }

        // Validate backup file
        $validation = $this->restore->validateBackup($backupFile, $type, $siteId);
        if (!$validation['valid']) {
            return response()->json([
                'error'      => 'فایل بکاپ معتبر نیست',
                'validation' => $validation,
            ], 422);
        }

        // ── Create restore log and token ───────────────────────────────────────

        $token = $this->restore->generateToken();

        $log = RestoreLog::create([
            'restore_type'      => $type,
            'site_id'           => $siteId,
            'backup_file'       => $backupFile,
            'status'            => RestoreLog::STATUS_PENDING,
            'dry_run'           => false,
            'restore_token'     => $token,
            'validation_result' => $validation,
            'executed_by'       => auth()->id(),
            'started_at'        => now(),
        ]);

        // ── Acquire lock ───────────────────────────────────────────────────────

        $lockValue = $siteId ? "tenant-{$siteId}" : $type;
        $ttl       = $type === RestoreLog::TYPE_PLATFORM_DATABASE ? 60 : 30;

        if (!$this->restore->acquireLock($lockValue, $ttl)) {
            $log->markFailed('نتوانستم قفل Redis را بگیرم — بازیابی دیگری شروع شد');
            return response()->json(['error' => 'تعارض قفل — دوباره تلاش کنید'], 409);
        }

        // ── Start restore worker (auto-managed) ────────────────────────────────

        if (!app()->environment('testing') && !$this->restore->startRestoreWorker()) {
            $this->restore->releaseLock();
            $log->markFailed('Restore worker could not be started');
            return response()->json(['error' => 'Restore worker در دسترس نیست؛ عملیات شروع نشد.'], 503);
        }

        // ── Dispatch appropriate job ───────────────────────────────────────────

        try {
            if ($type === RestoreLog::TYPE_PLATFORM_DATABASE) {
                RestorePlatformDatabaseJob::dispatch($log->id, $backupFile)
                    ->onConnection('restore')->onQueue('restore');
            } elseif ($type === RestoreLog::TYPE_TENANT_DATABASE) {
                RestoreTenantDatabaseJob::dispatch($log->id, $backupFile, $siteId)
                    ->onConnection('restore')->onQueue('restore');
            } elseif ($type === RestoreLog::TYPE_PLATFORM_STORAGE) {
                RestorePlatformStorageJob::dispatch($log->id, $backupFile, $mergeStrategy)
                    ->onConnection('restore')->onQueue('restore');
            }
        } catch (\Throwable $e) {
            $this->restore->stopRestoreWorker();
            $this->restore->releaseLock();
            $log->markFailed('Dispatch failed: ' . $e->getMessage());
            return response()->json(['error' => 'خطا در ارسال کار بازیابی: ' . $e->getMessage()], 500);
        }

        Log::info("RestoreWizard: dispatched restore job", [
            'type'    => $type,
            'file'    => $backupFile,
            'site_id' => $siteId,
            'log_id'  => $log->id,
            'token'   => $token,
            'user'    => auth()->id(),
        ]);

        return response()->json([
            'success'   => true,
            'token'     => $token,
            'log_id'    => $log->id,
            'stream_url'=> route('admin.disaster-recovery.stream', ['token' => $token]),
        ]);
    }

    // ─── SSE: Stream Progress ─────────────────────────────────────────────────

    public function stream(Request $request): StreamedResponse
    {
        $token = $request->query('token', '');

        if (!preg_match('/^[a-zA-Z0-9]{20,64}$/', $token)) {
            abort(400, 'Invalid token');
        }

        return response()->stream(function () use ($token) {
            $sent    = 0;
            $maxWait = 700; // seconds
            $start   = time();
            $done    = false;

            while (!$done && (time() - $start) < $maxWait) {
                $rawEvents = $this->restore->getProgressEvents($token, $sent);

                foreach ($rawEvents as $raw) {
                    echo "data: {$raw}\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                    $sent++;

                    $data = json_decode($raw, true);
                    if (isset($data['step']) && in_array($data['step'], ['done', 'error', 'timeout'], true)) {
                        $done = true;
                        break;
                    }
                }

                if (!$done) {
                    usleep(500_000); // 0.5s polling
                }
            }

            if (!$done) {
                echo "data: " . json_encode(['step' => 'timeout', 'message' => 'زمان انقضا شد', 'status' => 'warn']) . "\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    // ─── AJAX: Status ─────────────────────────────────────────────────────────

    public function status()
    {
        $locked = $this->restore->isLocked();
        return response()->json([
            'locked'      => $locked,
            'lock_value'  => $locked ? $this->restore->getLockValue() : null,
        ]);
    }

    // ─── POST: Abort ──────────────────────────────────────────────────────────

    public function abort(Request $request)
    {
        if (!$this->restore->isLocked()) {
            return response()->json(['message' => 'هیچ بازیابی فعالی یافت نشد'], 200);
        }

        $activeLog = RestoreLog::whereIn('status', [RestoreLog::STATUS_PENDING, RestoreLog::STATUS_RUNNING])
            ->latest('id')
            ->first();

        if (!$activeLog || $activeLog->status === RestoreLog::STATUS_RUNNING) {
            return response()->json([
                'error' => 'بازیابی در حال اجرا را نمی‌توان با آزادکردن lock متوقف کرد؛ lock برای جلوگیری از اجرای همزمان حفظ شد.',
            ], 409);
        }

        $lockValue = $this->restore->getLockValue();
        $activeLog->markAborted('Restore aborted before worker execution by operator');
        $this->restore->stopRestoreWorker();
        $this->restore->releaseLock();

        Log::warning("RestoreWizard: restore manually aborted", [
            'lock_value' => $lockValue,
            'user'       => auth()->id(),
            'ip'         => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "بازیابی «{$lockValue}» لغو شد. قفل Redis آزاد شد.",
        ]);
    }

    // ─── GET: Restore Logs ────────────────────────────────────────────────────

    public function logs(Request $request)
    {
        $query = RestoreLog::with('site', 'executor')->latest();

        if ($type = $request->query('type')) {
            $query->where('restore_type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $logs = $query->paginate(25);

        return view('admin.disaster-recovery.logs', compact('logs'));
    }

    // ─── GET: Log Detail ──────────────────────────────────────────────────────

    public function logShow(RestoreLog $log)
    {
        $log->load('site', 'executor');
        return view('admin.disaster-recovery.log-show', compact('log'));
    }
}
