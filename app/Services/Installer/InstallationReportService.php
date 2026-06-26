<?php
namespace App\Services\Installer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class InstallationReportService
{
    const REPORTS_DIR = 'installation-reports';

    public function __construct(
        private HealthCheckRunner $health,
    ) {}

    // ─── Data Collection ─────────────────────────────────────────────────────

    public function collect(): array
    {
        $installationId = Str::uuid()->toString();

        $data = [
            'installation_id'   => $installationId,
            'generated_at'      => now(),
            'project_name'      => config('app.name', 'SaaS Platform'),
            'project_version'   => $this->getProjectVersion(),
            'git_commit'        => $this->getGitCommit(),
            'environment'       => app()->environment(),
            'domain'            => $this->getDomain(),
            'server_ip'         => $this->getServerIp(),
            'app_url'           => config('app.url'),

            // Versions
            'laravel_version'   => app()->version(),
            'php_version'       => PHP_VERSION,
            'composer_version'  => $this->getComposerVersion(),
            'node_version'      => $this->getNodeVersion(),

            // Database
            'db_engine'         => 'MySQL / MariaDB',
            'db_version'        => $this->getDbVersion(),

            // Timezones
            'app_timezone'      => config('app.timezone', 'UTC'),
            'server_timezone'   => date_default_timezone_get(),

            // Infrastructure checks
            'redis_status'      => $this->checkRedis(),
            'queue_status'      => $this->checkQueue(),
            'scheduler_status'  => $this->checkScheduler(),
            'supervisor_status' => $this->checkSupervisor(),
            'reverb_status'     => $this->checkReverb(),
            'storage_status'    => $this->checkStorage(),
            'mail_status'       => $this->checkMail(),
            'arvan_status'      => $this->checkArvan(),
            'license_status'    => $this->checkLicense(),
            'backup_status'     => $this->checkBackup(),

            // Modules
            'installed_modules' => $this->getInstalledModules(),

            // Admin (no secrets)
            'super_admin'       => $this->getSuperAdmin(),

            // Health
            'dr_result'         => $this->runDrCheck(),
            'install_date_jalali' => $this->getJalaliDate(),
            'install_time'        => now()->format('H:i:s'),
        ];

        $data['health_score'] = $this->calculateHealthScore($data);
        $data['dr_score']     = $data['dr_result']['dr_score'] ?? 0;

        // Signature
        $payload = $data;
        unset($payload['generated_at']);
        $data['checksum'] = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));
        $data['generated_by'] = 'Platform Installer v1.0 / Claude';

        return $data;
    }

    // ─── HTML Generation ─────────────────────────────────────────────────────

    public function generateHtml(array $data): array
    {
        $dir = storage_path('app/' . self::REPORTS_DIR);
        @mkdir($dir, 0755, true);

        $timestamp = now()->format('Ymd_His');
        $filename  = "INSTALLATION_REPORT_{$timestamp}.html";
        $path      = "{$dir}/{$filename}";

        try {
            $html = view('installer.reports.installation-report', compact('data'))->render();
            $ok   = file_put_contents($path, $html) !== false;

            return [
                'success'  => $ok,
                'path'     => $ok ? $path : null,
                'filename' => $ok ? $filename : null,
                'message'  => $ok ? 'HTML report saved' : 'Failed to save HTML report',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'path' => null, 'filename' => null, 'message' => $e->getMessage()];
        }
    }

    // ─── PDF Generation ──────────────────────────────────────────────────────

    public function generatePdf(array $data): array
    {
        $dir = storage_path('app/' . self::REPORTS_DIR);
        @mkdir($dir, 0755, true);

        $timestamp = now()->format('Ymd_His');
        $filename  = "INSTALLATION_REPORT_{$timestamp}.pdf";
        $path      = "{$dir}/{$filename}";

        try {
            if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                return ['success' => false, 'path' => null, 'filename' => null, 'message' => 'DomPDF not installed'];
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('installer.reports.installation-report-pdf', compact('data'));
            $pdf->setPaper('A4', 'portrait');
            $pdf->save($path);

            return [
                'success'  => true,
                'path'     => $path,
                'filename' => $filename,
                'message'  => 'PDF report saved',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'path' => null, 'filename' => null, 'message' => $e->getMessage()];
        }
    }

    // ─── Infrastructure Checks ───────────────────────────────────────────────

    private function checkRedis(): array
    {
        try {
            Redis::ping();
            return ['status' => 'pass', 'value' => 'Connected', 'label' => 'Redis'];
        } catch (\Throwable) {
            return ['status' => 'failed', 'value' => 'Connection refused', 'label' => 'Redis'];
        }
    }

    private function checkQueue(): array
    {
        $driver = config('queue.default', 'sync');
        if ($driver === 'sync') {
            return ['status' => 'warning', 'value' => 'sync (not recommended)', 'label' => 'Queue'];
        }
        return ['status' => 'pass', 'value' => $driver, 'label' => 'Queue'];
    }

    private function checkScheduler(): array
    {
        $lockFile = storage_path('framework/schedule-' . sha1(app()->environmentFilePath()));
        if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 200) {
            return ['status' => 'pass', 'value' => 'Running (< 3 min ago)', 'label' => 'Scheduler'];
        }
        return ['status' => 'warning', 'value' => 'Add cron entry manually', 'label' => 'Scheduler'];
    }

    private function checkSupervisor(): array
    {
        $output = [];
        $code   = 0;
        exec('supervisorctl status saas-platform-worker 2>&1', $output, $code);
        $out = implode(' ', $output);
        if (stripos($out, 'RUNNING') !== false) {
            return ['status' => 'pass', 'value' => 'RUNNING', 'label' => 'Supervisor'];
        }
        return ['status' => 'warning', 'value' => trim($out) ?: 'Not running', 'label' => 'Supervisor'];
    }

    private function checkReverb(): array
    {
        $output = [];
        exec('supervisorctl status saas-platform-reverb 2>&1', $output);
        $out = implode(' ', $output);
        if (stripos($out, 'RUNNING') !== false) {
            return ['status' => 'pass', 'value' => 'RUNNING', 'label' => 'Reverb'];
        }
        return ['status' => 'warning', 'value' => 'Not running / not configured', 'label' => 'Reverb'];
    }

    private function checkStorage(): array
    {
        $link = public_path('storage');
        if (is_link($link) && file_exists($link)) {
            return ['status' => 'pass', 'value' => 'Symlink active', 'label' => 'Storage Link'];
        }
        return ['status' => 'warning', 'value' => 'Run: php artisan storage:link', 'label' => 'Storage Link'];
    }

    private function checkMail(): array
    {
        $mailer = config('mail.default', 'log');
        if ($mailer === 'log') {
            return ['status' => 'warning', 'value' => 'log (not configured)', 'label' => 'Mail'];
        }
        $host = config("mail.mailers.{$mailer}.host", '');
        if (empty($host)) {
            return ['status' => 'warning', 'value' => 'MAIL_HOST not set', 'label' => 'Mail'];
        }
        return ['status' => 'pass', 'value' => "{$mailer} / {$host}", 'label' => 'Mail'];
    }

    private function checkArvan(): array
    {
        $key    = config('services.arvan.access_key') ?? config('arvan.access_key', '');
        $bucket = config('services.arvan.bucket') ?? config('arvan.bucket', '');
        if (empty($key) || empty($bucket)) {
            return ['status' => 'warning', 'value' => 'Not configured', 'label' => 'Arvan CDN/Storage'];
        }
        return ['status' => 'pass', 'value' => "Bucket: {$bucket}", 'label' => 'Arvan CDN/Storage'];
    }

    private function checkLicense(): array
    {
        try {
            if (class_exists(\App\Services\LicenseService::class)) {
                $svc   = app(\App\Services\LicenseService::class);
                $valid = method_exists($svc, 'isValid') ? $svc->isValid() : true;
                return ['status' => $valid ? 'pass' : 'warning', 'value' => $valid ? 'Valid' : 'Invalid', 'label' => 'License'];
            }
        } catch (\Throwable) {}
        return ['status' => 'warning', 'value' => 'License service unavailable', 'label' => 'License'];
    }

    private function checkBackup(): array
    {
        $dir = storage_path('backups');
        if (!is_dir($dir)) {
            return ['status' => 'warning', 'value' => 'Backup directory not found', 'label' => 'Backup'];
        }
        $files = glob("{$dir}/*.{sql,sql.gz,zip}", GLOB_BRACE) ?: [];
        if (empty($files)) {
            return ['status' => 'warning', 'value' => 'No backups yet', 'label' => 'Backup'];
        }
        $age = round((time() - max(array_map('filemtime', $files))) / 3600);
        return [
            'status' => $age < 48 ? 'pass' : 'warning',
            'value'  => "Last backup: {$age}h ago",
            'label'  => 'Backup',
        ];
    }

    // ─── Data Helpers ────────────────────────────────────────────────────────

    private function getInstalledModules(): array
    {
        $modules     = [];
        $packagesDir = base_path('packages/saas-platform/modules');
        if (!is_dir($packagesDir)) {
            return $modules;
        }
        foreach (scandir($packagesDir) as $dir) {
            if ($dir[0] === '.' || !is_dir("{$packagesDir}/{$dir}")) {
                continue;
            }
            $cf      = "{$packagesDir}/{$dir}/composer.json";
            $name    = $dir;
            $version = '1.0.0';
            if (file_exists($cf)) {
                $pkg     = json_decode(file_get_contents($cf), true);
                $name    = $pkg['name'] ?? $dir;
                $version = $pkg['version'] ?? '1.0.0';
            }
            $modules[] = ['name' => $name, 'version' => $version, 'status' => 'installed'];
        }
        return $modules;
    }

    private function getSuperAdmin(): array
    {
        try {
            $admin = DB::table('users')->where('is_admin', 1)->first();
            if ($admin) {
                return [
                    'name'   => $admin->name ?? 'Super Admin',
                    'mobile' => $admin->mobile ?? $admin->email ?? '—',
                ];
            }
        } catch (\Throwable) {}
        return ['name' => 'Not set', 'mobile' => '—'];
    }

    private function runDrCheck(): array
    {
        try {
            return $this->health->runFull();
        } catch (\Throwable $e) {
            return ['dr_score' => 0, 'dr_grade' => 'F', 'pass' => false, 'summary' => $e->getMessage()];
        }
    }

    private function calculateHealthScore(array $data): int
    {
        $checks = [
            $data['redis_status']['status']      ?? 'failed',
            $data['queue_status']['status']      ?? 'failed',
            $data['storage_status']['status']    ?? 'failed',
            $data['mail_status']['status']       ?? 'warning',
            $data['supervisor_status']['status'] ?? 'warning',
            $data['reverb_status']['status']     ?? 'warning',
            $data['arvan_status']['status']      ?? 'warning',
            $data['backup_status']['status']     ?? 'warning',
            $data['license_status']['status']    ?? 'warning',
            $data['scheduler_status']['status']  ?? 'warning',
        ];

        $total = count($checks) * 2;
        $score = 0;
        foreach ($checks as $s) {
            if ($s === 'pass')    $score += 2;
            if ($s === 'warning') $score += 1;
        }

        return (int) round(($score / $total) * 100);
    }

    private function getProjectVersion(): string
    {
        $pj = base_path('package.json');
        if (file_exists($pj)) {
            $pkg = json_decode(file_get_contents($pj), true);
            if (!empty($pkg['version'])) {
                return $pkg['version'];
            }
        }
        return config('app.version', '1.0.0');
    }

    private function getGitCommit(): string
    {
        $output = [];
        exec('git rev-parse --short HEAD 2>&1', $output);
        $commit = $output[0] ?? '';
        return preg_match('/^[0-9a-f]{6,}$/i', $commit) ? $commit : 'N/A';
    }

    private function getComposerVersion(): string
    {
        $output = [];
        exec('composer --version 2>&1', $output);
        if (!empty($output[0]) && preg_match('/Composer version ([\d.]+)/', $output[0], $m)) {
            return $m[1];
        }
        return trim($output[0] ?? 'N/A');
    }

    private function getNodeVersion(): string
    {
        $output = [];
        exec('node --version 2>&1', $output);
        return trim($output[0] ?? 'N/A');
    }

    private function getDbVersion(): string
    {
        try {
            $row = DB::select('SELECT VERSION() as v');
            return $row[0]->v ?? 'N/A';
        } catch (\Throwable) {
            return 'N/A (not connected)';
        }
    }

    private function getDomain(): string
    {
        if (!empty($_SERVER['SERVER_NAME'])) {
            return $_SERVER['SERVER_NAME'];
        }
        $url = config('app.url', '');
        return parse_url($url, PHP_URL_HOST) ?: 'localhost';
    }

    private function getServerIp(): string
    {
        if (!empty($_SERVER['SERVER_ADDR'])) {
            return $_SERVER['SERVER_ADDR'];
        }
        $output = [];
        exec('hostname -I 2>&1', $output);
        if (!empty($output[0])) {
            return trim(explode(' ', trim($output[0]))[0]);
        }
        return gethostbyname(gethostname() ?: 'localhost') ?: 'N/A';
    }

    private function getJalaliDate(): string
    {
        if (function_exists('date_fa')) {
            return date_fa(now(), 'Y/m/d');
        }
        if (class_exists(\Hekmatinasser\Verta\Verta::class)) {
            return \Hekmatinasser\Verta\Verta::now()->format('Y/m/d');
        }
        return now()->format('Y-m-d');
    }
}
