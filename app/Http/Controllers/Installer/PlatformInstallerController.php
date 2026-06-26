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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class PlatformInstallerController extends Controller
{
    public function __construct(
        private InstallerService        $installer,
        private InstallerValidator      $validator,
        private EnvironmentWriter       $envWriter,
        private InfrastructureInstaller $infra,
        private RestoreAdapter          $restore,
        private UpgradeAdapter          $upgrade,
        private HealthCheckRunner       $health,
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

        if (!auth()->check() || !auth()->user()->is_admin) {
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
        $this->installer->lock([
            'mode'         => InstallerService::MODE_FRESH,
            'completed_at' => now()->toISOString(),
            'admin_id'     => $state['data']['platform']['admin_id'] ?? null,
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
            'source'    => 'required|in:local,arvan,release',
            'file_path' => 'required|string',
        ]);

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

        $rollback = $this->restore->createRollbackPoint('pre_restore');

        $sourceData    = $this->installer->getStepData('source_select');
        $restoreService = $this->restore->getRestoreService();

        // Acquire restore lock
        $token = \Illuminate\Support\Str::uuid()->toString();
        if (!$restoreService->acquireLock($token)) {
            return back()->withErrors(['restore' => 'یک عملیات بازیابی در حال اجرا است — لطفاً صبر کنید']);
        }

        $this->installer->saveStepData('restore', [
            'token'         => $token,
            'rollback_path' => $rollback['rollback_path'] ?? null,
            'source'        => $sourceData['source'],
            'path'          => $sourceData['path'],
            'started_at'    => now()->toISOString(),
        ]);

        $this->installer->markStepCompleted('restore');
        return redirect()->route('installer.step', ['mode' => 'restore', 'step' => 'finish']);
    }

    private function stepRestoreFinish(array $data)
    {
        return view('installer.restore.finish', $data);
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
        return view('installer.complete', compact('lockData'));
    }

    // ─── AJAX: Re-run env checks ─────────────────────────────────────────────

    public function apiCheck(Request $request)
    {
        $check = $request->input('check');
        $checks = $this->validator->runAll();
        return response()->json($checks[$check] ?? ['status' => 'fail', 'message' => 'بررسی نامعتبر']);
    }
}
