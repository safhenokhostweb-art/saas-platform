<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Installer\InstallerService;
use App\Services\Installer\BaselineManifestService;
use App\Services\Installer\UpgradeAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * Read-only admin panel for the Platform Installer.
 *
 * Security invariants:
 * - Never exposes .env content or secrets
 * - No write, delete, or overwrite operations
 * - Only Super Admin (is_admin = true) may access
 * - Verification is read-only (hash checks, no file modification)
 */
class InstallerAdminController extends Controller
{
    public function __construct(
        private InstallerService        $installer,
        private BaselineManifestService $manifestService,
        private UpgradeAdapter          $upgrade,
    ) {}

    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function index()
    {
        $lockData    = $this->installer->getLockData();
        $isLocked    = $this->installer->isLocked();
        $baselines   = $this->manifestService->listBaselines($this->upgrade->getBaselinesRootPath());
        $primaryBaseline = $this->resolvePrimaryBaseline($lockData, $baselines);

        // Quick manifest status for the primary baseline
        $manifestStatus = null;
        if ($primaryBaseline && file_exists($primaryBaseline['path'] . '/manifest.json')) {
            $manifestStatus = $this->quickManifestStatus($primaryBaseline['path']);
        }

        $hasHtml = !empty($lockData['report_html']) && file_exists($lockData['report_html']);
        $hasPdf  = !empty($lockData['report_pdf'])  && file_exists($lockData['report_pdf']);

        return view('admin.installer.index', compact(
            'lockData',
            'isLocked',
            'baselines',
            'primaryBaseline',
            'manifestStatus',
            'hasHtml',
            'hasPdf',
        ));
    }

    // ─── Baseline Verification (read-only) ───────────────────────────────────

    public function verify(Request $request)
    {
        $baselinePath = $request->input('path');

        // Ensure the path is inside the expected baselines root — no path traversal
        $root = realpath($this->upgrade->getBaselinesRootPath());
        $real = realpath($baselinePath ?? '');

        if (!$real || !$root || !str_starts_with($real, $root)) {
            return response()->json([
                'pass'   => false,
                'status' => 'FAILED',
                'error'  => 'Invalid baseline path',
            ], 422);
        }

        $result = $this->manifestService->verify($real);

        return response()->json($result);
    }

    // ─── Report Downloads ─────────────────────────────────────────────────────

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

    // ─── Manifest Download ────────────────────────────────────────────────────

    public function downloadManifest(Request $request)
    {
        $baselinePath = $request->input('path', $this->getPrimaryBaselinePath());

        // Path traversal guard
        $root = realpath($this->upgrade->getBaselinesRootPath());
        $real = realpath((string) $baselinePath);

        if (!$real || !$root || !str_starts_with($real, $root)) {
            abort(403, 'Invalid baseline path');
        }

        $manifestPath = $real . '/manifest.json';
        if (!file_exists($manifestPath)) {
            abort(404, 'manifest.json یافت نشد');
        }

        return response()->download(
            $manifestPath,
            basename($real) . '_manifest.json',
            ['Content-Type' => 'application/json']
        );
    }

    // ─── Baseline Meta Download ───────────────────────────────────────────────

    public function downloadBaselineMeta(Request $request)
    {
        $baselinePath = $request->input('path', $this->getPrimaryBaselinePath());

        // Path traversal guard
        $root = realpath($this->upgrade->getBaselinesRootPath());
        $real = realpath((string) $baselinePath);

        if (!$real || !$root || !str_starts_with($real, $root)) {
            abort(403, 'Invalid baseline path');
        }

        $metaPath = $real . '/baseline_meta.json';
        if (!file_exists($metaPath)) {
            abort(404, 'baseline_meta.json یافت نشد');
        }

        return response()->download(
            $metaPath,
            basename($real) . '_baseline_meta.json',
            ['Content-Type' => 'application/json']
        );
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function resolvePrimaryBaseline(?array $lockData, array $baselines): ?array
    {
        // Prefer the baseline stored in the lock file
        if (!empty($lockData['baseline_path'])) {
            foreach ($baselines as $b) {
                if (rtrim($b['path'], '/\\') === rtrim($lockData['baseline_path'], '/\\')) {
                    return $b;
                }
            }
        }

        // Fall back to INITIAL_INSTALLATION_BASELINE
        foreach ($baselines as $b) {
            if ($b['is_initial']) {
                return $b;
            }
        }

        // Last resort: most recently created
        return $baselines[count($baselines) - 1] ?? null;
    }

    private function getPrimaryBaselinePath(): ?string
    {
        $lockData = $this->installer->getLockData();
        if (!empty($lockData['baseline_path']) && is_dir($lockData['baseline_path'])) {
            return $lockData['baseline_path'];
        }
        $initial = $this->upgrade->getBaselinesRootPath() . '/INITIAL_INSTALLATION_BASELINE';
        return is_dir($initial) ? $initial : null;
    }

    /**
     * Quick manifest status check without full checksum computation.
     * Only verifies required files exist and manifest.json is valid JSON.
     */
    private function quickManifestStatus(string $baselineDir): array
    {
        $manifestPath = $baselineDir . '/manifest.json';
        if (!file_exists($manifestPath)) {
            return ['status' => 'MISSING', 'pass' => false];
        }
        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            return ['status' => 'INVALID_JSON', 'pass' => false];
        }
        // Check required files are listed in checksums
        $checksums = $manifest['checksums'] ?? [];
        $missing = [];
        foreach (BaselineManifestService::REQUIRED_FILES as $f) {
            if (!isset($checksums[$f]) && !file_exists($baselineDir . '/' . $f)) {
                $missing[] = $f;
            }
        }
        if (!empty($missing)) {
            return ['status' => 'INCOMPLETE', 'pass' => false, 'missing' => $missing];
        }
        return [
            'status'            => 'OK',
            'pass'              => true,
            'manifest_checksum' => $manifest['manifest_checksum'] ?? null,
            'installation_uuid' => $manifest['installation_uuid'] ?? null,
            'created_at_jalali' => $manifest['created_at_jalali'] ?? null,
            'platform_version'  => $manifest['platform_version'] ?? null,
        ];
    }
}
