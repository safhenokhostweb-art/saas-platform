<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\LocalRecoveryLog;
use App\Models\Site;
use App\Services\Installer\EnvironmentWriter;
use App\Services\Installer\LocalPcPackageBuilder;
use App\Services\Installer\LocalRecoveryRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecoveryRepositoryController extends Controller
{
    public function __construct(
        private LocalRecoveryRepository $repo,
        private EnvironmentWriter       $envWriter,
        private LocalPcPackageBuilder   $pcBuilder,
    ) {}

    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View
    {
        $status          = $this->repo->getStatus();
        $platformBackups = $this->repo->listPlatformBackups();
        $siteFilter      = $request->integer('site_id') ?: null;
        $customerBackups = $this->repo->listCustomerBackups($siteFilter);
        $sites           = Site::orderBy('title')->get(['id', 'title', 'domain']);

        return view('admin.infrastructure.recovery-repository.index', compact(
            'status',
            'platformBackups',
            'customerBackups',
            'sites',
            'siteFilter',
        ));
    }

    // ─── Save Configuration ───────────────────────────────────────────────────

    public function save(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'local_path'  => ['nullable', 'string', 'max:512'],
            'policy'      => ['required', 'in:local,arvan,both'],
            'priority'    => ['required', 'in:local_first,arvan_first'],
            'min_free_gb' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $values = [
            'RECOVERY_POLICY'    => $request->policy,
            'RECOVERY_PRIORITY'  => $request->priority,
            'RECOVERY_MIN_FREE_GB' => (string) $request->integer('min_free_gb'),
        ];

        $path = trim($request->input('local_path', ''));
        $values['RECOVERY_LOCAL_PATH'] = $path; // always write — empty string clears the stored value

        $result = $this->envWriter->write($values);

        if (!$result['success']) {
            return back()->withErrors(['save' => 'ذخیره تنظیمات ناموفق: ' . $result['message']]);
        }

        return back()->with('success', 'تنظیمات خزانه بازیابی ذخیره شد.');
    }

    // ─── AJAX: Validate Path ──────────────────────────────────────────────────

    public function validatePath(Request $request): JsonResponse
    {
        $path   = trim($request->input('path', ''));
        $result = $this->repo->validate($path !== '' ? $path : $this->repo->getRepoPath());

        return response()->json($result);
    }

    // ─── On-Demand Verify ────────────────────────────────────────────────────

    public function verify(LocalRecoveryLog $log): JsonResponse
    {
        $result = $this->repo->verifyLog($log);

        return response()->json([
            'log_id'  => $log->id,
            'status'  => $log->fresh()->status,
            'pass'    => $result['pass'],
            'message' => $result['message'],
        ]);
    }

    // ─── Delete Log Entry ─────────────────────────────────────────────────────

    public function destroy(LocalRecoveryLog $log): \Illuminate\Http\RedirectResponse
    {
        // Remove files from disk
        if (is_dir($log->local_path)) {
            $this->deleteDirectory($log->local_path);
        }

        $log->delete();

        return back()->with('success', 'رکورد بازیابی و فایل‌های مربوطه حذف شدند.');
    }

    // ─── Verify All ───────────────────────────────────────────────────────────

    public function verifyAll(): JsonResponse
    {
        $logs    = LocalRecoveryLog::all();
        $results = [];

        foreach ($logs as $log) {
            $result    = $this->repo->verifyLog($log);
            $results[] = ['id' => $log->id, 'type' => $log->type, 'pass' => $result['pass'], 'message' => $result['message']];
        }

        $passed = collect($results)->where('pass', true)->count();
        $failed = collect($results)->where('pass', false)->count();

        return response()->json([
            'total'   => count($results),
            'passed'  => $passed,
            'failed'  => $failed,
            'results' => $results,
        ]);
    }

    // ─── LEVEL 3: Download to Local PC ───────────────────────────────────────

    public function downloadPlatformPackage(): StreamedResponse
    {
        $result = $this->pcBuilder->buildPlatformPackage();

        if (!$result['success']) {
            abort(500, $result['message']);
        }

        return $this->streamZipDownload($result['zip_path'], $result['filename']);
    }

    public function downloadFullPackage(): StreamedResponse
    {
        $result = $this->pcBuilder->buildFullPackage();

        if (!$result['success']) {
            abort(500, $result['message']);
        }

        return $this->streamZipDownload($result['zip_path'], $result['filename']);
    }

    private function streamZipDownload(string $zipPath, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($zipPath) {
            $handle = fopen($zipPath, 'rb');
            while (!feof($handle)) {
                echo fread($handle, 8 * 1024 * 1024); // 8MB chunks
                flush();
            }
            fclose($handle);
            @unlink($zipPath);
        }, $filename, [
            'Content-Type'        => 'application/zip',
            'Content-Length'      => filesize($zipPath),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
