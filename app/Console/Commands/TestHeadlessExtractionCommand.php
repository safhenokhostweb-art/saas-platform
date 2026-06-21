<?php

namespace App\Console\Commands;

use App\Services\ThemeBuilder\ReverseEngineering\PlaywrightExtractionService;
use App\Services\ThemeBuilder\ReverseEngineering\VisualReverseEngineeringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * TB3 Sprint 11 — Headless Extraction Test Command
 *
 * Usage:
 *   php artisan headless:test [url] [--skip-pipeline]
 *
 * Examples:
 *   php artisan headless:test https://digikala.com
 *   php artisan headless:test https://malltina.com --skip-pipeline
 *   php artisan headless:test   # runs default test suite
 */
class TestHeadlessExtractionCommand extends Command
{
    protected $signature   = 'headless:test
                              {url?                : URL to test (default: runs suite)}
                              {--skip-pipeline     : Only test Playwright, skip 7-phase RE pipeline}
                              {--timeout=30000     : Navigation timeout in ms}';

    protected $description = 'Test the Playwright headless extraction engine against one URL or the default suite';

    private const DEFAULT_SUITE = [
        ['url' => 'https://digikala.com',  'label' => 'Digikala (Next.js CSR)',  'expect_headless' => true],
        ['url' => 'https://malltina.com',  'label' => 'Malltina (Next.js SSR)',   'expect_headless' => true],
        ['url' => 'https://apple.com',     'label' => 'Apple (SSR)',              'expect_headless' => false],
        ['url' => 'https://amazon.com',    'label' => 'Amazon (SSR)',             'expect_headless' => false],
    ];

    public function __construct(
        private readonly PlaywrightExtractionService    $playwright,
        private readonly VisualReverseEngineeringService $reEngine,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->line('');
        $this->line('<fg=blue;options=bold>══════════════════════════════════════════════════</>');
        $this->line('<fg=blue;options=bold>   TB3 Sprint 11 — Headless Extraction Test</>');
        $this->line('<fg=blue;options=bold>══════════════════════════════════════════════════</>');
        $this->line('');

        // 1. Availability check
        $this->info('Checking Playwright availability...');
        $report = $this->playwright->availabilityReport();
        $this->renderAvailabilityTable($report);

        if (!$report['ready']) {
            $this->line('');
            $this->warn('Playwright is NOT ready. Install it first:');
            $this->line('  <fg=yellow>cd scripts/headless && npm install && npx playwright install chromium</>');
            $this->line('');
            return self::FAILURE;
        }

        $this->line('');
        $this->info('Playwright is ready. Running extraction tests...');
        $this->line('');

        $url     = $this->argument('url');
        $timeout = (int) $this->option('timeout');

        if ($url) {
            return $this->testSingle($url, $timeout);
        }

        return $this->testSuite($timeout);
    }

    // ── Single URL test ───────────────────────────────────────────────────────

    private function testSingle(string $url, int $timeout): int
    {
        $this->line("Testing: <fg=cyan>{$url}</>");

        if ($this->option('skip-pipeline')) {
            return $this->testPlaywrightOnly($url, $timeout);
        }

        return $this->testFullPipeline($url, $timeout);
    }

    private function testPlaywrightOnly(string $url, int $timeout): int
    {
        $start = microtime(true);

        try {
            $result = $this->playwright->extract($url, $timeout);
            $elapsed = round((microtime(true) - $start) * 1000);

            $comp = $result['components'] ?? [];
            $fw   = $result['framework']  ?? [];

            $this->line('');
            $this->line('<fg=green;options=bold>✓ Playwright extraction SUCCESS</fg=green;options=bold>');
            $this->line('');
            $this->renderExtractionReport($result, $elapsed);
            return self::SUCCESS;

        } catch (\Throwable $e) {
            $elapsed = round((microtime(true) - $start) * 1000);
            $this->line('');
            $this->error("✗ Extraction FAILED ({$elapsed}ms): " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function testFullPipeline(string $url, int $timeout): int
    {
        $start = microtime(true);

        try {
            $this->line('Running full 7-phase RE pipeline with headless extraction...');
            $job = $this->reEngine->fromUrl($url, '', '', true); // forceHeadless=true
            $elapsed = round((microtime(true) - $start) * 1000);

            $this->line('');
            if ($job->isComplete()) {
                $this->line('<fg=green;options=bold>✓ RE Pipeline COMPLETE</fg=green;options=bold>');
                $scores  = $job->similarity_scores ?? [];
                $quality = $job->quality_report    ?? [];

                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Extraction Mode',  $job->extractionModeLabel()],
                        ['Best Match',       $scores['best_match_label'] ?? '—'],
                        ['Best Score',       ($scores['best_match_score'] ?? 0) . '%'],
                        ['Overall Score',    ($scores['overall'] ?? 0) . '%'],
                        ['Quality Verdict',  $quality['verdict'] ?? 'UNKNOWN'],
                        ['Quality Score',    ($quality['score'] ?? 0) . '/100'],
                        ['Duration',         $elapsed . 'ms'],
                        ['Job ID',           $job->id],
                    ]
                );
            } else {
                $this->error('Pipeline ended with status: ' . $job->status);
                if ($job->error_message) {
                    $this->error($job->error_message);
                }
                return self::FAILURE;
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $elapsed = round((microtime(true) - $start) * 1000);
            $this->error("Pipeline FAILED ({$elapsed}ms): " . $e->getMessage());
            Log::error('headless:test pipeline failed', ['url' => $url, 'error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }

    // ── Default test suite ────────────────────────────────────────────────────

    private function testSuite(int $timeout): int
    {
        $results = [];
        $pass    = 0;
        $fail    = 0;

        foreach (self::DEFAULT_SUITE as $case) {
            $this->line("Testing: <fg=cyan>{$case['label']}</> ({$case['url']})");
            $start = microtime(true);

            try {
                $result  = $this->playwright->extract($case['url'], $timeout);
                $elapsed = round((microtime(true) - $start) * 1000);

                $comp = $result['components'] ?? [];
                $nav  = count($comp['nav']['items'] ?? []);
                $h1   = $comp['meta']['h1'] ?? '';
                $colors = count($comp['colors'] ?? []);
                $prod = $comp['product_grid']['count'] ?? 0;

                $results[] = [
                    'label'   => $case['label'],
                    'status'  => '<fg=green>PASS</>',
                    'nav'     => $nav . ' links',
                    'h1'      => mb_substr($h1, 0, 30) ?: '—',
                    'colors'  => $colors,
                    'products'=> $prod,
                    'time'    => $elapsed . 'ms',
                ];
                $pass++;
                $this->line("  <fg=green>✓</> ({$elapsed}ms) nav={$nav} h1=\"" . mb_substr($h1, 0, 40) . '" products=' . $prod);

            } catch (\Throwable $e) {
                $elapsed = round((microtime(true) - $start) * 1000);
                $results[] = [
                    'label'   => $case['label'],
                    'status'  => '<fg=red>FAIL</>',
                    'nav'     => '—',
                    'h1'      => mb_substr($e->getMessage(), 0, 30),
                    'colors'  => 0,
                    'products'=> 0,
                    'time'    => $elapsed . 'ms',
                ];
                $fail++;
                $this->line("  <fg=red>✗</> ({$elapsed}ms) " . $e->getMessage());
            }

            $this->line('');
        }

        // Summary table
        $this->line('');
        $this->line('<fg=white;options=bold>Suite Results:</fg=white;options=bold>');
        $this->table(
            ['Site', 'Status', 'Nav', 'H1 (30 chars)', 'Colors', 'Products', 'Time'],
            $results
        );

        $total = $pass + $fail;
        $this->line('');
        if ($fail === 0) {
            $this->line("<fg=green;options=bold>✓ ALL PASS: {$pass}/{$total}</fg=green;options=bold>");
            $this->line('');
            $this->line('<fg=green>HEADLESS_ENGINE_OPERATIONAL</fg=green>');
        } else {
            $this->line("<fg=yellow>PARTIAL: {$pass}/{$total} passed, {$fail} failed</fg=yellow>");
        }

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }

    // ── Rendering helpers ─────────────────────────────────────────────────────

    private function renderAvailabilityTable(array $report): void
    {
        $rows = [
            ['Node.js',       $report['node']       ? '<fg=green>✓ OK</>' : '<fg=red>✗ Missing</>'],
            ['extract.mjs',   $report['script']     ? '<fg=green>✓ OK</>' : '<fg=red>✗ Missing</>'],
            ['playwright npm', $report['playwright'] ? '<fg=green>✓ OK</>' : '<fg=red>✗ Not installed</>'],
            ['Overall',       $report['ready']      ? '<fg=green;options=bold>READY</>' : '<fg=red;options=bold>NOT READY</>'],
        ];
        $this->table(['Component', 'Status'], $rows);
    }

    private function renderExtractionReport(array $result, int $elapsed): void
    {
        $comp = $result['components'] ?? [];
        $fw   = $result['framework']  ?? [];
        $meta = $comp['meta'] ?? [];

        $this->table(
            ['Field', 'Value'],
            [
                ['URL',           $result['url'] ?? '—'],
                ['Title',         mb_substr($meta['title'] ?? '—', 0, 60)],
                ['H1',            mb_substr($meta['h1'] ?? '—', 0, 60)],
                ['Framework',     ($fw['is_nextjs'] ? 'Next.js' : ($fw['is_react'] ? 'React' : ($fw['is_vue'] ? 'Vue' : 'SSR/Unknown')))],
                ['HTML Size',     number_format(strlen($result['rendered_html'] ?? '')) . ' bytes'],
                ['Nav Links',     count($comp['nav']['items'] ?? [])],
                ['Mega Menu',     ($comp['mega_menu']['detected'] ?? false) ? 'Yes (' . ($comp['mega_menu']['columns'] ?? 0) . ' cols)' : 'No'],
                ['Hero H1',       mb_substr($comp['hero']['headline'] ?? '—', 0, 60)],
                ['Product Cards', ($comp['product_grid']['count'] ?? 0) . ' (cols: ' . ($comp['product_grid']['columns'] ?? '?') . ')'],
                ['Card Types',    implode(', ', $comp['cards']['types'] ?? []) ?: '—'],
                ['Search',        ($comp['search']['found'] ?? false) ? 'Yes' : 'No'],
                ['Filters',       ($comp['filters']['found'] ?? false) ? 'Yes (' . count($comp['filters']['items'] ?? []) . ' items)' : 'No'],
                ['Sliders',       implode(', ', $comp['sliders'] ?? []) ?: '—'],
                ['Colors',        count($comp['colors'] ?? []) . ' extracted'],
                ['Screenshot',    !empty($result['screenshot_base64']) ? 'Yes (' . strlen($result['screenshot_base64']) . ' b64 chars)' : 'No'],
                ['Duration',      $elapsed . 'ms'],
            ]
        );
    }
}
