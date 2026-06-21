<?php

namespace App\Services\ThemeBuilder\ReverseEngineering;

use Illuminate\Support\Facades\Log;

/**
 * TB3 Sprint 11 — Playwright Headless Extraction Service
 *
 * Spawns the Node.js Playwright extractor script and parses its output.
 * Gracefully degrades: if Node/Playwright is unavailable, callers fall back to HTTP mode.
 *
 * Script: scripts/headless/extract.mjs
 * Install: cd scripts/headless && npm install && npx playwright install chromium
 */
class PlaywrightExtractionService
{
    // Seconds to wait for the Node process before killing it
    private const PROCESS_TIMEOUT = 90;

    // The extractor script, relative to base_path()
    private const SCRIPT     = 'scripts/headless/extract.mjs';
    private const SCRIPT_BAT = 'scripts/headless/run.bat';

    // node_modules location for Playwright (in the scripts/headless sub-project)
    private const PLAYWRIGHT_NM = 'scripts/headless/node_modules/playwright';

    // System Chrome paths to try when Playwright's own Chromium is not installed
    private const SYSTEM_CHROME_PATHS = [
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/chromium-browser',
        '/usr/bin/chromium',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ];

    // ── Availability check ────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->hasNode() && $this->hasScript() && $this->hasPlaywright();
    }

    public function availabilityReport(): array
    {
        $chromePath = $this->findSystemChrome();
        return [
            'node'         => $this->hasNode(),
            'script'       => $this->hasScript(),
            'playwright'   => $this->hasPlaywright(),
            'ready'        => $this->isAvailable(),
            'chrome_path'  => $chromePath,
            'using_system' => $chromePath !== null && !is_dir(base_path(self::PLAYWRIGHT_NM . '/chromium')),
        ];
    }

    // ── Extraction ────────────────────────────────────────────────────────────

    /**
     * Extract full rendered DOM + screenshot from a URL using Playwright.
     *
     * @param  string $url       Target URL
     * @param  int    $timeoutMs Navigation timeout in ms (default 30 000)
     * @param  string $device    Viewport device: desktop (default) | tablet | mobile | all
     * @return array             Playwright result payload
     * @throws \RuntimeException On Node process failure
     */
    public function extract(string $url, int $timeoutMs = 30000, string $device = 'desktop'): array
    {
        $scriptPath = base_path(self::SCRIPT);
        $node       = $this->findNodeBinary();
        $chromePath = $this->findSystemChrome();

        // On Windows, stream_set_blocking(false) for proc_open pipes does NOT work —
        // fread() blocks indefinitely when Chrome (child of node) still holds the write
        // end of the pipe open. Fix: redirect stdout/stderr to temp files on Windows.
        // On Linux pipes work correctly with non-blocking reads.
        $useTmpFiles = PHP_OS_FAMILY === 'Windows';
        $tmpOut = $useTmpFiles ? tempnam(sys_get_temp_dir(), 'pw_out_') : null;
        $tmpErr = $useTmpFiles ? tempnam(sys_get_temp_dir(), 'pw_err_') : null;

        if ($useTmpFiles) {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['file', $tmpOut, 'w'],
                2 => ['file', $tmpErr, 'w'],
            ];
        } else {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
        }

        // Set Playwright env vars in PHP's own process env so they're inherited.
        // OPENSSL_CONF is confirmed absent from PHP CLI env on this system.
        if ($chromePath) {
            putenv('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=' . $chromePath);
        }
        putenv('PLAYWRIGHT_BROWSERS_PATH=' . base_path('scripts/headless/.playwright-browsers'));

        $process = proc_open(
            [$node, $scriptPath, $url, (string) $timeoutMs, $device],
            $descriptors,
            $pipes,
            base_path(),
            null  // inherit PHP's full environment (includes all Windows system vars)
        );

        if (!is_resource($process)) {
            if ($tmpOut) @unlink($tmpOut);
            if ($tmpErr) @unlink($tmpErr);
            throw new \RuntimeException('Playwright: فرآیند Node.js راه‌اندازی نشد.');
        }

        // Close stdin; we won't write to it
        if (isset($pipes[0])) fclose($pipes[0]);
        if (!$useTmpFiles) {
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
        }

        $stdout  = '';
        $stderr  = '';
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
            Log::warning('PlaywrightExtractor: Node process exited non-zero', [
                'url'      => $url,
                'exit'     => $exitCode,
                'stderr'   => mb_substr($stderr, 0, 500),
            ]);
            throw new \RuntimeException('Playwright: ' . $errMsg);
        }

        $result = json_decode($stdout, true);
        if (!is_array($result) || empty($result['success'])) {
            throw new \RuntimeException('Playwright: خروجی JSON نامعتبر — ' . mb_substr($stdout, 0, 200));
        }

        Log::info('PlaywrightExtractor: success', [
            'url'       => $url,
            'html_size' => strlen($result['rendered_html'] ?? ''),
            'screenshot'=> !empty($result['screenshot_base64']),
        ]);

        return $result;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

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
        return file_exists(base_path(self::SCRIPT));
    }

    private function hasPlaywright(): bool
    {
        // Playwright npm package installed
        if (is_dir(base_path(self::PLAYWRIGHT_NM))) return true;

        // Playwright-core installed (lighter, no bundled browser) AND system Chrome available
        if (is_dir(base_path('scripts/headless/node_modules/playwright-core'))
            && $this->findSystemChrome() !== null) {
            return true;
        }

        return false;
    }

    public function findSystemChrome(): ?string
    {
        // Env override first
        $envPath = getenv('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH') ?: '';
        if ($envPath && file_exists($envPath)) return $envPath;

        // LOCALAPPDATA Chrome path on Windows
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
            // Last resort: find node.exe in PATH using where.exe
            $out = [];
            exec('where.exe node.exe 2>NUL', $out);
            if (!empty($out[0]) && file_exists($out[0])) return $out[0];
            return 'node';
        }
        return 'node';
    }

    private function buildPath(): string
    {
        $existing = getenv('PATH') ?: '';
        $extras   = PHP_OS_FAMILY === 'Windows'
            ? 'C:\\Program Files\\nodejs;C:\\laragon\\bin\\nodejs\\node-v22\\;'
            : '/usr/local/bin:/usr/bin:/bin:';
        return $extras . $existing;
    }

    private function parseStderrJson(string $stderr): string
    {
        if (empty($stderr)) return '';
        $data = json_decode(trim($stderr), true);
        return is_array($data) ? ($data['error'] ?? '') : '';
    }
}
