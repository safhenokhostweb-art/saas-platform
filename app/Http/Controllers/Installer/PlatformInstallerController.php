<?php
namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Services\Installer\InstallerService;
use App\Services\Installer\InstallerValidator;
use App\Services\Installer\EnvironmentWriter;
use App\Services\Installer\InfrastructureInstaller;
use App\Services\Installer\RestoreAdapter;
use App\Services\Installer\UpgradeAdapter;
use App\Services\Installer\HealthCheckRunner;
use App\Services\Installer\InstallationReportService;
use App\Services\Installer\LocalPcPackageBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class PlatformInstallerController extends Controller
{
    public function __construct(
        private InstallerService          $installer,
        private InstallerValidator        $validator,
        private EnvironmentWriter         $envWriter,
        private InfrastructureInstaller   $infra,
        private RestoreAdapter            $restore,
        private UpgradeAdapter            $upgrade,
        private HealthCheckRunner         $health,
        private InstallationReportService $report,
        private LocalPcPackageBuilder     $pcBuilder,
    ) {}

    // ─── Entry Point ─────────────────────────────────────────────────────────

    public function index()
    {
        return view('installer.mode-select');
    }

    // ─── Locked ──────────────────────────────────────────────────────────────

    public function locked()
    {
        $lockData = $this->installer->getLockData();
        return view('installer.locked', compact('lockData'));
    }

    public function unlock(Request $request)
    {
        $request->validate(['confirm' => 'required|in:UNLOCK-INSTALLER']);

        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            abort(403, 'فقط Super Admin می‌تواند installer را باز کند');
        }

        $this->installer->unlock();
        $this->installer->clearState();

        return redirect()->route('installer.index')
            ->with('success', 'Installer باز شد. می‌توانید از ابتدا نصب کنید.');
    }

    // ─── Mode Selection ──────────────────────────────────────────────────────

    public function selectMode(Request $request)
    {
        $request->validate([
            'mode' => 'required|in:fresh,restore,upgrade',
        ]);

        $mode = $request->mode;
        $this->installer->initMode($mode);

        return redirect()->route('installer.step', [
            'mode' => $mode,
            'step' => $this->installer->getStepsForMode($mode)[0],
        ]);
    }

    // ─── Wizard Step Router ──────────────────────────────────────────────────

    public function step(string $mode, string $step)
    {
        $steps       = $this->installer->getStepsForMode($mode);
        $stepIndex   = $this->installer->getStepIndex($mode, $step);
        $stepLabels  = array_combine($steps, array_map(
            fn($s) => $this->installer->getStepLabel($s),
            $steps
        ));
        $completedSteps = $this->installer->getState()['completed_steps'] ?? [];
        $stepData       = $this->installer->getStepData($step);

        $viewData = compact('mode', 'step', 'steps', 'stepIndex', 'stepLabels', 'completedSteps', 'stepData');

        return match("{$mode}.{$step}") {
            'fresh.env_check'      => $this->stepFreshEnvCheck($viewData),
            'fresh.database'       => $this->stepFreshDatabase($viewData),
            'fresh.environment'    => $this->stepFreshEnvironment($viewData),
            'fresh.platform'       => $this->stepFreshPlatform($viewData),
            'fresh.infrastructure' => $this->stepFreshInfrastructure($viewData),
            'fresh.validation'     => $this->stepFreshValidation($viewData),
            'fresh.finish'         => $this->stepFreshFinish($viewData),
            'restore.source_select'=> $this->stepRestoreSourceSelect($viewData),
            'restore.verify'       => $this->stepRestoreVerify($viewData),
            'restore.restore'      => $this->stepRestoreRestore($viewData),
            'restore.finish'       => $this->stepRestoreFinish($viewData),
            'upgrade.snapshot'     => $this->stepUpgradeSnapshot($viewData),
            'upgrade.upgrade'      => $this->stepUpgradeRun($viewData),
            'upgrade.validate'     => $this->stepUpgradeValidate($viewData),
            'upgrade.finish'       => $this->stepUpgradeFinish($viewData),
            default                => abort(404, "مرحله نامعتبر: {$mode}.{$step}"),
        };
    }

    public function processStep(Request $request, string $mode, string $step)
    {
        return match("{$mode}.{$step}") {
            'fresh.env_check'      => $this->processFreshEnvCheck($request),
            'fresh.database'       => $this->processFreshDatabase($request),
            'fresh.environment'    => $this->processFreshEnvironment($request),
            'fresh.platform'       => $this->processFreshPlatform($request),
            'fresh.infrastructure' => $this->processFreshInfrastructure($request),
            'fresh.validation'     => $this->processFreshValidation($request),
            'fresh.finish'         => $this->processFreshFinish($request),
            'restore.source_select'=> $this->processRestoreSourceSelect($request),
            'restore.verify'       => $this->processRestoreVerify($request),
            'restore.restore'      => $this->processRestoreRestore($request),
            'upgrade.snapshot'     => $this->processUpgradeSnapshot($request),
            'upgrade.upgrade'      => $this->processUpgradeRun($request),
            'upgrade.validate'     => $this->processUpgradeValidate($request),
            default                => back()->withErrors(['step' => 'مرحله نامعتبر']),
        };
    }

    // ─── FRESH INSTALL Steps ─────────────────────────────────────────────────

    private function stepFreshEnvCheck(array $data)
    {
        $checks = $this->validator->runAll();
        $summary = $this->validator->summary($checks);
        $hasCritical = $this->validator->hasCriticalFailures($checks);
        return view('installer.fresh.env-check', array_merge($data, compact('checks', 'summary', 'hasCritical')));
    }

    private function processFreshEnvCheck(Request $request)
    {
        $checks = $this->validator->runAll();
        if ($this->validator->hasCriticalFailures($checks)) {
            return back()->withErrors(['check' => 'بررسی محیط ناموفق — خطاهای بحرانی را برطرف کنید']);
        }
        $this->installer->markStepCompleted('env_check');
        return redirect()->route('installer.step', ['mode' => 'fresh', 'step' => 'database']);
    }

    private function stepFreshDatabase(array $data)
    {
        return view('installer.fresh.database', $data);
    }

    private function processFreshDatabase(Request $request)
    {
        $request->validate([
            'db_host'     => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.\-_]+$/'],
            'db_port'     => ['nullable', 'integer', 'between:1,65535'],
            'db_name'     => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'db_user'     => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'db_password' => ['nullable', 'string', 'max:128'],
        ]);

        $result = $this->envWriter->writeDatabaseConfig([
            'host'     => $request->db_host,
            'port'     => $request->db_port ?? 3306,
            'database' => $request->db_name,
            'username' => $request->db_user,
            'password' => $request->db_password ?? '',
        ]);

        if (!$result['success']) {
            return back()->withErrors(['db' => $result['message']]);
        }

        $this->installer->markStepCompleted('database');
        $this->installer->saveStepData('database', $request->only('db_host', 'db_name', 'db_user'));
        return redirect()->route('installer.step', ['mode' => 'fresh', 'step' => 'environment']);
    }

    private function stepFreshEnvironment(array $data)
    {
        $currentAppKey = $this->envWriter->get('APP_KEY');
        return view('installer.fresh.environment', array_merge($data, compact('currentAppKey')));
    }

    private function processFreshEnvironment(Request $request)
    {
        $request->validate([
            'app_name'    => 'required|string|max:100',
            'app_url'     => 'required|url|max:255',
            'app_timezone'=> 'required|string|max:64',
            'queue_driver'=> 'required|in:redis,database,sync',
            'cache_driver'=> 'required|in:redis,file',
        ]);

        $values = [
            'APP_NAME'       => $request->app_name,
            'APP_URL'        => $request->app_url,
            'APP_TIMEZONE'   => $request->app_timezone,
            'QUEUE_CONNECTION'=> $request->queue_driver,
            'CACHE_DRIVER'   => $request->cache_driver,
        ];

        if (empty($this->envWriter->get('APP_KEY'))) {
            $values['APP_KEY'] = $this->envWriter->generateAppKey();
        }

        $result = $this->envWriter->write($values);
        if (!$result['success']) {
            return back()->withErrors(['env' => $result['message']]);
        }

        $this->installer->markStepCompleted('environment');
        return redirect()->route('installer.step', ['mode' => 'fresh', 'step' => 'platform']);
    }

    private function stepFreshPlatform(array $data)
    {
        return view('installer.fresh.platform', $data);
    }

    private function processFreshPlatform(Request $request)
    {
        $request->validate([
            'admin_name'     => 'required|string|max:100',
            'admin_mobile'   => ['required', 'regex:/^09[0-9]{9}$/'],
            'admin_password' => 'required|min:8|confirmed',
        ]);

        // Run migrations
        $migrateResult = $this->infra->runMigrations(true);
        if (!$migrateResult['success']) {
            return back()->withErrors(['migrate' => 'Migration ناموفق: ' . $migrateResult['message']]);
        }

        // Create super admin
        $adminResult = $this->infra->createSuperAdmin([
            'name'     => $request->admin_name,
            'mobile'   => $request->admin_mobile,
            'password' => $request->admin_password,
        ]);
        if (!$adminResult['success']) {
            return back()->withErrors(['admin' => $adminResult['message']]);
        }

        // Platform settings
        $this->infra->createPlatformSettings();

        $this->installer->markStepCompleted('platform');
        $this->installer->saveStepData('platform', ['admin_id' => $adminResult['user_id']]);
        return redirect()->route('installer.step', ['mode' => 'fresh', 'step' => 'infrastructure']);
    }

    private function stepFreshInfrastructure(array $data)
    {
        $supervisorConfig = $this->infra->generateSupervisorConfig();
        $reverbConfig     = $this->infra->generateReverbConfig();
        $cronLine         = $this->infra->generateCronLine();
        return view('installer.fresh.infrastructure', array_merge(
            $data,
            compact('supervisorConfig', 'reverbConfig', 'cronLine')
        ));
    }

    private function processFreshInfrastructure(Request $request)
    {
        $this->installer->markStepCompleted('infrastructure');
        return redirect()->route('installer.step', ['mode' => 'fresh', 'step' => 'validation']);
    }

    private function stepFreshValidation(array $data)
    {
        $healthResult = $this->health->runFull();
        return view('installer.fresh.validation', array_merge($data, compact('healthResult')));
    }

    private function processFreshValidation(Request $request)
    {
        $this->installer->markStepCompleted('validation');
        return redirect()->route('installer.step', ['mode' => 'fresh', 'step' => 'finish']);
    }

    private function stepFreshFinish(array $data)
    {
        return view('installer.fresh.finish', $data);
    }

    private function processFreshFinish(Request $request)
    {
        $state = $this->installer->getState();

        // Phase 1: Collect base report data (without baseline info)
        $reportData = $this->report->collect();

        // Phase 2: Create baseline core (database + .env + meta + manifest)
        $baselineResult = $this->upgrade->createInstallationBaseline(
            $reportData['installation_id']
        );

        // Phase 3: Enrich report data with real baseline info (path, checksum, verification)
        $reportData = $this->report->enrichWithBaseline($reportData, $baselineResult);

        // Phase 4: Generate HTML/PDF reports (now include baseline sections)
        $htmlResult = $this->report->generateHtml($reportData);
        $pdfResult  = $this->report->generatePdf($reportData);

        // Phase 5: Finalize baseline (copy reports → update manifest → lock read-only)
        $finalizeResult = null;
        if (!empty($baselineResult['baseline_path'])) {
            $finalizeResult = $this->upgrade->addReportsToBaseline(
                $baselineResult['baseline_path'],
                $htmlResult['path'] ?? null,
                $pdfResult['path']  ?? null,
            );
        }

        // Lock installer — store all paths
        $this->installer->lock([
            'mode'              => InstallerService::MODE_FRESH,
            'completed_at'      => now()->toISOString(),
            'admin_id'          => $state['data']['platform']['admin_id'] ?? null,
            'report_html'       => $htmlResult['path']               ?? null,
            'report_pdf'        => $pdfResult['path']                ?? null,
            'report_id'         => $reportData['installation_id']    ?? null,
            'baseline_path'     => $baselineResult['baseline_path']  ?? null,
            'baseline_label'    => $baselineResult['baseline_label'] ?? null,
            'manifest_checksum' => $finalizeResult['manifest_checksum']
                                    ?? $baselineResult['manifest_checksum']
                                    ?? null,
            'overall_status'    => $reportData['overall_status'] ?? null,
            'certification'     => $reportData['installation_certification'] ?? null,
        ]);
        $this->installer->clearState();

        return redirect()->route('installer.complete');
    }

    // ─── RESTORE Steps ───────────────────────────────────────────────────────

    private function stepRestoreSourceSelect(array $data)
    {
        $sources = $this->restore->listAllSources();
        return view('installer.restore.source-select', array_merge($data, compact('sources')));
    }

    private function processRestoreSourceSelect(Request $request)
    {
        $request->validate([
            'source'    => 'required|in:local,arvan,release,recovery_repository,local_pc',
            'file_path' => 'required_unless:source,local_pc|nullable|string',
            'pc_zip'    => 'required_if:source,local_pc|nullable|file|mimes:zip|max:5242880', // 5GB
        ]);

        if ($request->source === 'local_pc') {
            $upload = $request->file('pc_zip');

            $uploadDir = storage_path('app/pc-restore-uploads');
            @mkdir($uploadDir, 0750, true);

            $uploadPath = $uploadDir . '/' . uniqid('pc_upload_', true) . '.zip';
            $upload->move($uploadDir, basename($uploadPath));

            $result = $this->pcBuilder->extractAndVerify($uploadPath);
            @unlink($uploadPath); // raw zip no longer needed once extracted

            if (!$result['valid']) {
                return back()->withErrors(['source' => 'بسته Local PC نامعتبر است: ' . $result['message']]);
            }

            $this->installer->saveStepData('source_select', [
                'source' => 'local_pc',
                'path'   => $result['db_path'],
                'extract_dir' => $result['extract_dir'],
            ]);
            $this->installer->markStepCompleted('source_select');

            return redirect()->route('installer.step', ['mode' => 'restore', 'step' => 'verify']);
        }

        $this->installer->saveStepData('source_select', [
            'source' => $request->source,
            'path'   => $request->file_path,
        ]);
        $this->installer->markStepCompleted('source_select');

        return redirect()->route('installer.step', ['mode' => 'restore', 'step' => 'verify']);
    }

    private function stepRestoreVerify(array $data)
    {
        $sourceData = $this->installer->getStepData('source_select');
        $verification = [];
        if (!empty($sourceData)) {
            $verification = $this->restore->verifyBackup($sourceData['source'], $sourceData['path']);
        }
        return view('installer.restore.verify', array_merge($data, compact('sourceData', 'verification')));
    }

    private function processRestoreVerify(Request $request)
    {
        $sourceData   = $this->installer->getStepData('source_select');
        $verification = $this->restore->verifyBackup($sourceData['source'], $sourceData['path']);

        if (!$verification['valid']) {
            return back()->withErrors(['verify' => 'تأیید پشتیبان ناموفق: ' . $verification['message']]);
        }

        $this->installer->markStepCompleted('verify');
        return redirect()->route('installer.step', ['mode' => 'restore', 'step' => 'restore']);
    }

    private function stepRestoreRestore(array $data)
    {
        $sourceData = $this->installer->getStepData('source_select');
        return view('installer.restore.restore', array_merge($data, compact('sourceData')));
    }

    private function processRestoreRestore(Request $request)
    {
        $request->validate(['confirm' => 'required|in:RESTORE-PLATFORM']);

        $sourceData     = $this->installer->getStepData('source_select');
        $source         = $sourceData['source'] ?? '';
        $path           = $sourceData['path'] ?? '';
        $restoreService = $this->restore->getRestoreService();

        // Acquire restore lock
        $token = \Illuminate\Support\Str::uuid()->toString();
        if (!$restoreService->acquireLock($token)) {
            return back()->withErrors(['restore' => 'یک عملیات بازیابی در حال اجرا است — لطفاً صبر کنید']);
        }

        // Local sources: execute synchronously (no queue dependency — safe for fresh-server DR)
        $localSources = ['local', 'local_pc', 'release', 'recovery_repository'];
        if (in_array($source, $localSources, true)) {
            set_time_limit(600); // allow up to 10 min for large DBs

            $result = $restoreService->executeLocalPlatformDatabaseRestore(null, $path);

            // Clean up extracted local_pc directory
            if ($source === 'local_pc' && !empty($sourceData['extract_dir'])) {
                $this->pcBuilder->deleteDirectory($sourceData['extract_dir']);
            }

            $this->installer->saveStepData('restore', [
                'token'      => $token,
                'source'     => $source,
                'path'       => $path,
                'started_at' => now()->toISOString(),
                'success'    => $result['success'],
                'message'    => $result['message'],
            ]);
            $this->installer->markStepCompleted('restore');

            return redirect()->route('installer.step', ['mode' => 'restore', 'step' => 'finish']);
        }

        // Arvan source: dispatch to queue (requires running restore worker)
        if ($source === 'arvan') {
            $log = \App\Models\Infrastructure\RestoreLog::create([
                'restore_type'  => \App\Models\Infrastructure\RestoreLog::TYPE_PLATFORM_DATABASE,
                'status'        => \App\Models\Infrastructure\RestoreLog::STATUS_PENDING,
                'backup_file'   => $path,
                'restore_token' => $token,
                'dry_run'       => false,
                'executed_by'   => auth()->id(),
                'started_at'    => now(),
            ]);

            if (!app()->environment('testing') && !$restoreService->startRestoreWorker()) {
                $restoreService->releaseLock();
                $log->markFailed('Restore worker could not be started');
                return back()->withErrors(['restore' => 'Restore worker در دسترس نیست؛ عملیات شروع نشد.']);
            }

            try {
                \App\Jobs\RestorePlatformDatabaseJob::dispatch($log->id, $path)
                    ->onConnection('restore')->onQueue('restore');
            } catch (\Throwable $e) {
                $restoreService->stopRestoreWorker();
                $restoreService->releaseLock();
                return back()->withErrors(['restore' => 'خطا در ارسال کار بازیابی: ' . $e->getMessage()]);
            }

            $this->installer->saveStepData('restore', [
                'token'      => $token,
                'source'     => $source,
                'path'       => $path,
                'log_id'     => $log->id,
                'started_at' => now()->toISOString(),
                'queued'     => true,
            ]);
            $this->installer->markStepCompleted('restore');

            return redirect()->route('installer.step', ['mode' => 'restore', 'step' => 'finish']);
        }

        $restoreService->releaseLock();
        return back()->withErrors(['restore' => 'منبع بازیابی نامعتبر است']);
    }

    private function stepRestoreFinish(array $data)
    {
        $restoreStepData = $this->installer->getStepData('restore') ?? [];
        return view('installer.restore.finish', array_merge($data, compact('restoreStepData')));
    }

    // ─── UPGRADE Steps ───────────────────────────────────────────────────────

    private function stepUpgradeSnapshot(array $data)
    {
        return view('installer.upgrade.snapshot', $data);
    }

    private function processUpgradeSnapshot(Request $request)
    {
        $snapshotResult = $this->upgrade->createSnapshot();

        if (!$snapshotResult['success']) {
            return back()->withErrors(['snapshot' => $snapshotResult['message']]);
        }

        $this->installer->saveStepData('snapshot', $snapshotResult);
        $this->installer->markStepCompleted('snapshot');

        return redirect()->route('installer.step', ['mode' => 'upgrade', 'step' => 'upgrade']);
    }

    private function stepUpgradeRun(array $data)
    {
        $snapshotData = $this->installer->getStepData('snapshot');
        return view('installer.upgrade.upgrade', array_merge($data, compact('snapshotData')));
    }

    private function processUpgradeRun(Request $request)
    {
        $request->validate(['confirm' => 'required|in:UPGRADE-PLATFORM']);

        $snapshotData   = $this->installer->getStepData('snapshot');
        $snapshotPath   = $snapshotData['snapshot_path'] ?? '';
        $upgradeResult  = $this->upgrade->runUpgrade($snapshotPath);

        $this->installer->saveStepData('upgrade', $upgradeResult);

        if (!$upgradeResult['success'] && ($upgradeResult['needs_rollback'] ?? false)) {
            $rollbackResult = $this->upgrade->rollback($snapshotPath);
            return back()->withErrors([
                'upgrade' => 'ارتقاء ناموفق — Rollback اجرا شد: ' . ($rollbackResult['message'] ?? ''),
            ]);
        }

        $this->installer->markStepCompleted('upgrade');
        return redirect()->route('installer.step', ['mode' => 'upgrade', 'step' => 'validate']);
    }

    private function stepUpgradeValidate(array $data)
    {
        $healthResult = $this->health->runFull();
        return view('installer.upgrade.validate', array_merge($data, compact('healthResult')));
    }

    private function processUpgradeValidate(Request $request)
    {
        $this->installer->markStepCompleted('validate');
        return redirect()->route('installer.step', ['mode' => 'upgrade', 'step' => 'finish']);
    }

    private function stepUpgradeFinish(array $data)
    {
        return view('installer.upgrade.finish', $data);
    }

    // ─── Complete ────────────────────────────────────────────────────────────

    public function complete()
    {
        $lockData = $this->installer->getLockData();
        $hasHtml  = !empty($lockData['report_html']) && file_exists($lockData['report_html']);
        $hasPdf   = !empty($lockData['report_pdf'])  && file_exists($lockData['report_pdf']);
        return view('installer.complete', compact('lockData', 'hasHtml', 'hasPdf'));
    }

    // ─── Report Download ─────────────────────────────────────────────────────

    public function downloadReport(string $format)
    {
        if (!in_array($format, ['html', 'pdf'])) {
            abort(404);
        }

        $lockData = $this->installer->getLockData();
        $path = $format === 'html'
            ? ($lockData['report_html'] ?? null)
            : ($lockData['report_pdf']  ?? null);

        if (!$path || !file_exists($path)) {
            abort(404, 'گزارش نصب یافت نشد');
        }

        $mime     = $format === 'pdf' ? 'application/pdf' : 'text/html';
        $filename = 'INSTALLATION_REPORT.' . $format;

        return response()->download($path, $filename, ['Content-Type' => $mime]);
    }

    // ─── AJAX: Re-run env checks ─────────────────────────────────────────────

    public function apiCheck(Request $request)
    {
        $check = $request->input('check');
        $checks = $this->validator->runAll();
        return response()->json($checks[$check] ?? ['status' => 'fail', 'message' => 'بررسی نامعتبر']);
    }
}
