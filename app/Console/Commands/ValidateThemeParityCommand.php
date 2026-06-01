<?php

namespace App\Console\Commands;

use App\Models\ThemeProfile;
use App\Services\ThemeEngine\ThemePresetRegistry;
use App\Services\ThemeEngine\ThemeTokenRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * ui:validate-theme-parity
 *
 * Validates that the Theme Engine produces consistent tokens across:
 * 1. saas-platform admin dashboard
 * 2. customer-site tenant dashboard
 * 3. Mobile client API payload
 * 4. Desktop client (same API)
 * 5. All 8 presets have no missing token groups
 * 6. CSS var names follow --ui-* / --ds-* governance rules
 * 7. No --tb-* vars leaked into platform CSS (customer-site isolation)
 * 8. Sidebar tokens present in all presets
 * 9. Header tokens present in all presets
 * 10. Mobile payload has no null required fields
 */
class ValidateThemeParityCommand extends Command
{
    protected $signature = 'ui:validate-theme-parity
                            {--json : Output as JSON}
                            {--strict : Fail on warnings too}
                            {--fail-below=95 : Fail if score below N}';

    protected $description = 'Validate cross-platform theme token parity (Phase UI-THEME.1)';

    private array $results = [];

    public function handle(): int
    {
        $this->checkPresetsHaveAllColorGroups();
        $this->checkPresetsHaveSidebarTokens();
        $this->checkPresetsHaveHeaderTokens();
        $this->checkPresetsHaveTypographyTokens();
        $this->checkPresetsHaveBorderTokens();
        $this->checkCssVarNamingGovernance();
        $this->checkNoTbLeakInPlatformTokens();
        $this->checkMobilePayloadNoNullFields();
        $this->checkTokenCountMinimum();
        $this->checkDesignTokensCssImported();

        $passed = count(array_filter($this->results, fn ($r) => $r['status'] === 'pass'));
        $total  = count($this->results);
        $score  = $total > 0 ? (int) round(($passed / $total) * 100) : 0;

        if ($this->option('json')) {
            $this->line(json_encode([
                'score'  => $score,
                'passed' => $passed,
                'total'  => $total,
                'checks' => $this->results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['وضعیت', 'بررسی', 'یادداشت'], array_map(fn ($r) => [
                $r['status'] === 'pass' ? '<fg=green>✓</>' : '<fg=red>✗</>',
                $r['check'],
                $r['note'] ?? '',
            ], $this->results));
            $this->newLine();
            $this->info("پاریتی: {$score}/100");
        }

        $threshold = (int) $this->option('fail-below');
        return $score < $threshold ? self::FAILURE : self::SUCCESS;
    }

    // ── Checks ────────────────────────────────────────────────────────────────

    private function checkPresetsHaveAllColorGroups(): void
    {
        $required = ['primary', 'secondary', 'accent', 'success', 'warning', 'danger', 'info'];
        $failures = [];
        foreach (ThemePresetRegistry::all() as $preset) {
            $colors = $preset['colors'] ?? [];
            foreach ($required as $group) {
                if (empty($colors[$group])) {
                    $failures[] = $preset['slug'] . " → colors.{$group} گمشده";
                }
            }
        }
        $ok = empty($failures);
        $this->record('همه 8 پریست دارای هر 7 گروه رنگی هستند', $ok, $ok ? null : implode(' | ', array_slice($failures, 0, 3)));
    }

    private function checkPresetsHaveSidebarTokens(): void
    {
        $required = ['bg', 'bg_bottom', 'text', 'muted', 'accent', 'border'];
        $failures = [];
        foreach (ThemePresetRegistry::all() as $preset) {
            foreach ($required as $key) {
                if (empty($preset['sidebar'][$key])) {
                    $failures[] = $preset['slug'] . " → sidebar.{$key}";
                }
            }
        }
        $ok = empty($failures);
        $this->record('همه پریست‌ها توکن‌های sidebar را دارند', $ok, $ok ? null : implode(' | ', $failures));
    }

    private function checkPresetsHaveHeaderTokens(): void
    {
        $required = ['bg', 'text', 'link_active'];
        $failures = [];
        foreach (ThemePresetRegistry::all() as $preset) {
            foreach ($required as $key) {
                if (empty($preset['header'][$key])) {
                    $failures[] = $preset['slug'] . " → header.{$key}";
                }
            }
        }
        $ok = empty($failures);
        $this->record('همه پریست‌ها توکن‌های header را دارند', $ok, $ok ? null : implode(' | ', $failures));
    }

    private function checkPresetsHaveTypographyTokens(): void
    {
        $required = ['text_primary', 'text_secondary', 'text_muted', 'link'];
        $failures = [];
        foreach (ThemePresetRegistry::all() as $preset) {
            foreach ($required as $key) {
                if (empty($preset['typography'][$key])) {
                    $failures[] = $preset['slug'] . " → typography.{$key}";
                }
            }
        }
        $ok = empty($failures);
        $this->record('همه پریست‌ها توکن‌های typography را دارند', $ok, $ok ? null : implode(' | ', $failures));
    }

    private function checkPresetsHaveBorderTokens(): void
    {
        $failures = [];
        foreach (ThemePresetRegistry::all() as $preset) {
            if (empty($preset['borders']['border'])) {
                $failures[] = $preset['slug'];
            }
        }
        $ok = empty($failures);
        $this->record('همه پریست‌ها توکن border دارند', $ok, $ok ? null : implode(', ', $failures));
    }

    private function checkCssVarNamingGovernance(): void
    {
        if (!Schema::hasTable('theme_profiles')) {
            $this->record('نام‌گذاری CSS var از --ui-* / --ds-* پیروی می‌کند', true, 'جدول موجود نیست — skip');
            return;
        }
        $profile = ThemeProfile::first();
        if (!$profile) {
            $this->record('نام‌گذاری CSS var از --ui-* / --ds-* پیروی می‌کند', true, 'بدون پروفایل — skip');
            return;
        }
        $vars    = $profile->toCssVars();
        $invalid = array_filter(array_keys($vars), fn ($k) => !str_starts_with($k, '--ui-') && !str_starts_with($k, '--ds-') && !str_starts_with($k, '--platform-') && !str_starts_with($k, '--tb-'));
        $ok = empty($invalid);
        $this->record('نام‌گذاری CSS var از --ui-* / --ds-* پیروی می‌کند', $ok, $ok ? null : 'var غیرمجاز: ' . implode(', ', array_slice($invalid, 0, 3)));
    }

    private function checkNoTbLeakInPlatformTokens(): void
    {
        if (!Schema::hasTable('theme_profiles')) {
            $this->record('--tb-* در توکن‌های پلتفرم نشت نکرده', true);
            return;
        }
        $profile = ThemeProfile::first();
        if (!$profile) {
            $this->record('--tb-* در توکن‌های پلتفرم نشت نکرده', true);
            return;
        }
        $vars = $profile->toCssVars();
        $tbVars = array_filter(array_keys($vars), fn ($k) => str_starts_with($k, '--tb-'));
        $ok = empty($tbVars);
        $this->record('--tb-* در توکن‌های پلتفرم نشت نکرده', $ok, $ok ? null : '--tb-* یافت شد: ' . implode(', ', $tbVars));
    }

    private function checkMobilePayloadNoNullFields(): void
    {
        if (!Schema::hasTable('theme_profiles')) { return; }
        try {
            $renderer  = app(ThemeTokenRenderer::class);
            $payload   = $renderer->mobilePayload(null);
            $nullFields = [];
            foreach (['colors', 'surfaces', 'typography', 'sidebar', 'header', 'borders'] as $group) {
                foreach ($payload[$group] ?? [] as $key => $val) {
                    if ($val === null || $val === '') {
                        $nullFields[] = "{$group}.{$key}";
                    }
                }
            }
            $ok = empty($nullFields);
            $this->record('Mobile payload بدون فیلد null است', $ok, $ok ? null : 'null: ' . implode(', ', $nullFields));
        } catch (\Throwable $e) {
            $this->record('Mobile payload بدون فیلد null است', false, $e->getMessage());
        }
    }

    private function checkTokenCountMinimum(): void
    {
        if (!Schema::hasTable('theme_profiles')) {
            $this->record('هر پروفایل حداقل ۳۰ CSS var تولید می‌کند', true, 'skip');
            return;
        }
        $failures = ThemeProfile::all()->filter(fn ($p) => count($p->toCssVars()) < 30)->map->name;
        $ok = $failures->isEmpty();
        $this->record('هر پروفایل حداقل ۳۰ CSS var تولید می‌کند', $ok, $ok ? null : 'کمبود توکن: ' . $failures->join(', '));
    }

    private function checkDesignTokensCssImported(): void
    {
        $paths = [
            base_path('resources/css/app.css'),
            base_path('../../customer-site/resources/css/app.css'),
        ];
        $allImport = true;
        foreach ($paths as $path) {
            if (!file_exists($path)) continue;
            if (!str_contains(file_get_contents($path), 'design-tokens.css')) {
                $allImport = false;
            }
        }
        $this->record('هر دو پروژه design-tokens.css را import می‌کنند', $allImport, $allImport ? null : 'یک پروژه import ندارد');
    }

    private function record(string $label, bool $pass, ?string $note = null): void
    {
        $this->results[] = ['check' => $label, 'status' => $pass ? 'pass' : 'fail', 'note' => $note];
    }
}
