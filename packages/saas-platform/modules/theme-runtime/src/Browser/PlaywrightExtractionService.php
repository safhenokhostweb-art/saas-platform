<?php

namespace SaaSPlatform\ThemeRuntime\Browser;

use Illuminate\Support\Facades\Log;

/**
 * Phase 2h (ADR-029) — moved here from saas-platform's app tree so
 * customer-site can run the exact same real-browser quality verification
 * (Phase 2g) against its own tenant runtime, not just saas-platform's
 * Builder preview.
 *
 * The Node.js scripts and their `node_modules/playwright` installation are
 * NOT duplicated into this package (Node module resolution ties them to a
 * fixed install location, and Playwright's own browser binaries are large)
 * — they stay at saas-platform's `scripts/headless/`. A host supplies
 * `$scriptRoot` pointing at that directory; saas-platform defaults to its
 * own `base_path('scripts/headless')`, customer-site configures a relative
 * cross-repo path (`../saas-platform/scripts/headless`) — the same
 * sibling-checkout assumption its own composer.json symlink repos already
 * make explicit.
 *
 * Script: quality-check.mjs (Phase 2g's own script — real console/resource-
 * failure/overflow/contrast evidence for one viewport, one navigation).
 * extract.mjs (a separate, saas-platform-only Visual RE competitor-scraping
 * engine) is NOT part of this shared service.
 */
class PlaywrightExtractionService
{
    private const PROCESS_TIMEOUT = 90;
    private const QUALITY_SCRIPT = 'quality-check.mjs';

    private const SYSTEM_CHROME_PATHS = [
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/chromium-browser',
        '/usr/bin/chromium',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ];

    public function __construct(private readonly string $scriptRoot)
    {
    }

    // ── Availability check ───────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->hasNode() && $this->hasScript() && $this->hasPlaywright();
    }

    public function availabilityReport(): array
    {
        $chromePath = $this->findSystemChrome();
        return [
            'node' => $this->hasNode(),
            'script' => $this->hasScript(),
            'playwright' => $this->hasPlaywright(),
            'ready' => $this->isAvailable(),
            'chrome_path' => $chromePath,
            'using_system' => $chromePath !== null && !is_dir($this->scriptRoot . '/node_modules/playwright/chromium'),
            'script_root' => $this->scriptRoot,
        ];
    }

    // ── Quality verification ─────────────────────────────────────────────

    /**
     * @throws \RuntimeException On Node process failure — the caller must
     *   treat this as "verification unavailable", never as a silent pass.
     */
    public function runQualityCheck(string $url, string $viewport = 'desktop', int $timeoutMs = 30000): array
    {
        $result = $this->runScript(self::QUALITY_SCRIPT, [$url, (string) $timeoutMs, $viewport]);

        if (!is_array($result) || empty($result['success'])) {
            throw new \RuntimeException('Playwright quality check: خروجی JSON نامعتبر.');
        }

        return $result;
    }

    private function runScript(string $scriptRelativePath, array $args): array
    {
        $scriptPath = $this->scriptRoot . '/' . ltrim($scriptRelativePath, '/');
        $node = $this->findNodeBinary();
        $chromePath = $this->findSystemChrome();

        $useTmpFiles = PHP_OS_FAMILY === 'Windows';
        $tmpOut = $useTmpFiles ? tempnam(sys_get_temp_dir(), 'pw_out_') : null;
        $tmpErr = $useTmpFiles ? tempnam(sys_get_temp_dir(), 'pw_err_') : null;

        $descriptors = $useTmpFiles
            ? [0 => ['pipe', 'r'], 1 => ['file', $tmpOut, 'w'], 2 => ['file', $tmpErr, 'w']]
            : [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        if ($chromePath) {
            putenv('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=' . $chromePath);
        }
        putenv('PLAYWRIGHT_BROWSERS_PATH=' . $this->scriptRoot . '/.playwright-browsers');

        $process = proc_open(
            [$node, $scriptPath, ...$args],
            $descriptors,
            $pipes,
            $this->scriptRoot,
            null
        );

        if (!is_resource($process)) {
            if ($tmpOut) @unlink($tmpOut);
            if ($tmpErr) @unlink($tmpErr);
            throw new \RuntimeException('Playwright: فرآیند Node.js راه‌اندازی نشد.');
        }

        if (isset($pipes[0])) fclose($pipes[0]);
        if (!$useTmpFiles) {
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
        }

        $stdout = '';
        $stderr = '';
        $nodePid = null;
        $deadline = time() + self::PROCESS_TIMEOUT;

        while (true) {
            $status = proc_get_status($process);
            if ($nodePid === null && !empty($status['pid'])) {
                $nodePid = (int) $status['pid'];
            }
            if (!$status['running']) break;
            if (time() >= $deadline) {
                if (PHP_OS_FAMILY === 'Windows' && $nodePid) {
                    exec("taskkill /F /T /PID {$nodePid} 2>NUL");
                }
                proc_terminate($process, 9);
                proc_close($process);
                if ($tmpOut) @unlink($tmpOut);
                if ($tmpErr) @unlink($tmpErr);
                throw new \RuntimeException('Playwright: timeout بعد از ' . self::PROCESS_TIMEOUT . ' ثانیه.');
            }
            if (!$useTmpFiles) {
                $stdout .= (string) fread($pipes[1], 65536);
                $stderr .= (string) fread($pipes[2], 8192);
            }
            usleep(200_000);
        }

        $exitCode = proc_close($process);

        if ($useTmpFiles) {
            $stdout = (string) @file_get_contents($tmpOut);
            $stderr = (string) @file_get_contents($tmpErr);
            @unlink($tmpOut);
            @unlink($tmpErr);
        } else {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
        }

        if ($exitCode !== 0) {
            $errMsg = $this->parseStderrJson($stderr) ?: mb_substr($stderr, 0, 500);
            Log::warning('ThemeRuntime PlaywrightExtractionService: Node process exited non-zero', [
                'script' => $scriptRelativePath,
                'args' => $args,
                'exit' => $exitCode,
                'stderr' => mb_substr($stderr, 0, 500),
            ]);
            throw new \RuntimeException('Playwright: ' . $errMsg);
        }

        $result = json_decode($stdout, true);
        if (!is_array($result)) {
            throw new \RuntimeException('Playwright: خروجی JSON نامعتبر — ' . mb_substr($stdout, 0, 200));
        }

        return $result;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function hasNode(): bool
    {
        try {
            $node = $this->findNodeBinary();
            exec('"' . $node . '" --version 2>&1', $out, $code);
            return $code === 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasScript(): bool
    {
        return file_exists($this->scriptRoot . '/' . self::QUALITY_SCRIPT);
    }

    private function hasPlaywright(): bool
    {
        if (is_dir($this->scriptRoot . '/node_modules/playwright')) return true;

        if (is_dir($this->scriptRoot . '/node_modules/playwright-core')
            && $this->findSystemChrome() !== null) {
            return true;
        }

        return false;
    }

    public function findSystemChrome(): ?string
    {
        $envPath = getenv('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH') ?: '';
        if ($envPath && file_exists($envPath)) return $envPath;

        $localAppData = getenv('LOCALAPPDATA') ?: '';
        if ($localAppData) {
            $p = $localAppData . '\\Google\\Chrome\\Application\\chrome.exe';
            if (file_exists($p)) return $p;
        }

        foreach (self::SYSTEM_CHROME_PATHS as $path) {
            if (file_exists($path)) return $path;
        }

        return null;
    }

    private function findNodeBinary(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = [
                'C:\\Program Files\\nodejs\\node.exe',
                'C:\\laragon\\bin\\nodejs\\node-v*\\node.exe',
                getenv('APPDATA') . '\\nvm\\current\\node.exe',
                'C:\\Program Files (x86)\\nodejs\\node.exe',
            ];
            foreach ($candidates as $c) {
                if (str_contains($c, '*')) {
                    $matches = glob($c);
                    if (!empty($matches)) return $matches[0];
                } elseif ($c && file_exists($c)) {
                    return $c;
                }
            }
            $out = [];
            exec('where.exe node.exe 2>NUL', $out);
            if (!empty($out[0]) && file_exists($out[0])) return $out[0];
            return 'node';
        }
        return 'node';
    }

    private function parseStderrJson(string $stderr): string
    {
        if (empty($stderr)) return '';
        $data = json_decode(trim($stderr), true);
        return is_array($data) ? ($data['error'] ?? '') : '';
    }
}
