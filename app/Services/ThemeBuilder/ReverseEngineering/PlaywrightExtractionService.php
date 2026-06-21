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
     * @return array             Playwright result payload
     * @throws \RuntimeException On Node process failure
     */
    public function extract(string $url, int $timeoutMs = 30000): array
    {
        $scriptPath = base_path(self::SCRIPT);
        $node       = $this->findNodeBinary();
        $chromePath = $this->findSystemChrome();

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        // Environment strategy: pass null to inherit PHP's full process environment.
        // We confirmed that OPENSSL_CONF is NOT present in PHP's env when running as CLI
        // (getenv('OPENSSL_CONF') returns false). So inheriting is safe — Node.js 24
        // won't see a broken OPENSSL_CONF and won't crash with ncrypto::CSPRNG.
        // Chrome also needs the full Windows system env (COMPUTERNAME, ProgramData,
        // WINDIR, PUBLIC, etc.) which would be missing in a hand-built minimal env.
        // PLAYWRIGHT vars are set on the inherited env via actual putenv() calls so they
        // take effect for the child.
        if ($chromePath) {
            putenv('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=' . $chromePath);
        }
        putenv('PLAYWRIGHT_BROWSERS_PATH=' . base_path('scripts/headless/.playwright-browsers'));

        $process = proc_open(
            [$node, $scriptPath, $url, (string) $timeoutMs],
            $descriptors,
            $pipes,
            base_path(),
            null  // inherit PHP's full environment
        );

        // Restore env vars we just set temporarily
        if ($chromePath) {
            $existingChrome = getenv('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH');
            if ($existingChrome === $chromePath) putenv('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH');
        }

        if (!is_resource($process)) {
            throw new \RuntimeException('Playwright: فرآیند Node.js راه‌اندازی نشد.');
        }

        // Non-blocking reads with timeout
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout    = '';
        $stderr    = '';
        $deadline  = time() + self::PROCESS_TIMEOUT;

        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) break;
            if (time() >= $deadline) {
                proc_terminate($process, 9);
                throw new \RuntimeException('Playwright: timeout بعد از ' . self::PROCESS_TIMEOUT . ' ثانیه.');
            }
            $stdout .= (string) fread($pipes[1], 65536);
            $stderr .= (string) fread($pipes[2], 8192);
            usleep(200_000); // 200ms poll
        }

        // Drain remaining output after process exit
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

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
