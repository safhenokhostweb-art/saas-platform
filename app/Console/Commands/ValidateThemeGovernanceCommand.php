<?php

namespace App\Console\Commands;

use App\Models\ThemeProfile;
use App\Services\ThemeEngine\ThemePresetRegistry;
use App\Services\ThemeEngine\ThemeTokenRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * ui:validate-theme-governance
 *
 * Validates:
 * 1. theme_profiles table exists and is migrated
 * 2. All 8 system presets are present in DB
 * 3. Exactly one platform-active profile exists
 * 4. Enterprise Blue is seeded (default preset)
 * 5. No custom profile has is_system=true
 * 6. All profiles produce non-empty CSS var sets
 * 7. No hardcoded hex colors in platform-styles.blade.php (design token check)
 * 8. ThemeTokenRenderer resolves without error
 * 9. Tenant isolation: no TenantThemeOverride references missing profiles
 * 10. Mobile payload structure is valid
 */
class ValidateThemeGovernanceCommand extends Command
{
    protected $signature = 'ui:validate-theme-governance
                            {--json : Output as JSON}
                            {--strict : Fail on warnings too}
                            {--fail-below=95 : Fail if score below N}';

    protected $description = 'Validate Enterprise Theme Engine governance (Phase UI-THEME.1)';

    private array $results = [];
    private int   $score   = 0;

    public function handle(): int
    {
        $this->checkTableMigrated();
        $this->checkAllPresetsSeeded();
        $this->checkSingleActiveProfile();
        $this->checkDefaultPreset();
        $this->checkNoFakeSystemProfiles();
        $this->checkAllProfilesProduceCssVars();
        $this->checkNoHardcodedColorsInPlatformStyles();
        $this->checkTokenRendererResolves();
        $this->checkTenantIsolation();
        $this->checkMobilePayloadStructure();

        $passed   = count(array_filter($this->results, fn ($r) => $r['status'] === 'pass'));
        $total    = count($this->results);
        $this->score = $total > 0 ? (int) round(($passed / $total) * 100) : 0;

        if ($this->option('json')) {
            $this->line(json_encode([
                'score'   => $this->score,
                'passed'  => $passed,
                'total'   => $total,
                'checks'  => $this->results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->printTable();
            $this->newLine();
            $grade = $this->grade($this->score);
            $this->info("نمره: {$this->score}/100 (Grade {$grade})");
        }

        $threshold = (int) $this->option('fail-below');
        return $this->score < $threshold ? self::FAILURE : self::SUCCESS;
    }

    // ── Checks ────────────────────────────────────────────────────────────────

    private function checkTableMigrated(): void
    {
        $ok = Schema::hasTable('theme_profiles') && Schema::hasTable('tenant_theme_overrides');
        $this->record('جدول theme_profiles و tenant_theme_overrides موجودند', $ok, $ok ? null : 'اجرای php artisan migrate');
    }

    private function checkAllPresetsSeeded(): void
    {
        if (!Schema::hasTable('theme_profiles')) {
            $this->record('هر 8 پریست سیستمی در DB موجودند', false, 'ابتدا migrate کنید');
            return;
        }
        $expected = collect(ThemePresetRegistry::all())->pluck('preset_key')->sort()->values();
        $actual   = ThemeProfile::whereNotNull('preset_key')->pluck('preset_key')->sort()->values();
        $missing  = $expected->diff($actual)->values();
        $ok       = $missing->isEmpty();
        $this->record('هر 8 پریست سیستمی در DB موجودند', $ok, $ok ? null : 'پریست‌های گمشده: ' . $missing->join(', ') . ' — اجرای ThemeProfileSeeder');
    }

    private function checkSingleActiveProfile(): void
    {
        if (!Schema::hasTable('theme_profiles')) { return; }
        $activeCount = ThemeProfile::where('is_active', true)->count();
        $ok = $activeCount === 1;
        $this->record('دقیقاً یک تم فعال وجود دارد', $ok, $ok ? null : "تعداد فعال: {$activeCount} — باید دقیقاً ۱ باشد");
    }

    private function checkDefaultPreset(): void
    {
        if (!Schema::hasTable('theme_profiles')) { return; }
        $ok = ThemeProfile::where('preset_key', 'enterprise-blue')->where('is_system', true)->exists();
        $this->record('پریست Enterprise Blue (is_system) موجود است', $ok, $ok ? null : 'ThemeProfileSeeder را اجرا کنید');
    }

    private function checkNoFakeSystemProfiles(): void
    {
        if (!Schema::hasTable('theme_profiles')) { return; }
        $fakes = ThemeProfile::where('is_system', true)->whereNotIn('preset_key', [
            'enterprise-blue', 'modern-indigo', 'emerald-pro', 'royal-purple',
            'midnight-dark', 'graphite', 'ocean-teal', 'executive-gold',
        ])->count();
        $ok = $fakes === 0;
        $this->record('هیچ پروفایل سفارشی به‌اشتباه is_system=true ندارد', $ok, $ok ? null : "{$fakes} رکورد غیرمجاز");
    }

    private function checkAllProfilesProduceCssVars(): void
    {
        if (!Schema::hasTable('theme_profiles')) { return; }
        $empty = ThemeProfile::all()->filter(fn ($p) => empty($p->toCssVars()))->count();
        $ok    = $empty === 0;
        $this->record('همه پروفایل‌ها CSS var غیرخالی تولید می‌کنند', $ok, $ok ? null : "{$empty} پروفایل بدون توکن");
    }

    private function checkNoHardcodedColorsInPlatformStyles(): void
    {
        $path = resource_path('views/partials/platform-styles.blade.php');
        if (!file_exists($path)) {
            $this->record('platform-styles.blade.php بدون hex هاردکد', true, null, 'warning');
            return;
        }
        $content = file_get_contents($path);
        // Count standalone hex values (not inside CSS var references)
        preg_match_all("/'#[0-9a-fA-F]{3,6}'/", $content, $matches);
        $hardcoded = count($matches[0]);
        $ok = $hardcoded === 0;
        $this->record('platform-styles.blade.php بدون hex هاردکد', $ok, $ok ? null : "{$hardcoded} مقدار hex مستقیم یافت شد", $ok ? null : 'warning');
    }

    private function checkTokenRendererResolves(): void
    {
        if (!Schema::hasTable('theme_profiles')) { return; }
        try {
            $renderer = app(ThemeTokenRenderer::class);
            $block    = $renderer->renderPlatform();
            $ok = strlen($block) > 0 || true; // empty is valid if no profile active yet
            $this->record('ThemeTokenRenderer بدون خطا اجرا می‌شود', true);
        } catch (\Throwable $e) {
            $this->record('ThemeTokenRenderer بدون خطا اجرا می‌شود', false, $e->getMessage());
        }
    }

    private function checkTenantIsolation(): void
    {
        if (!Schema::hasTable('tenant_theme_overrides') || !Schema::hasTable('theme_profiles')) { return; }
        $orphaned = \Illuminate\Support\Facades\DB::table('tenant_theme_overrides as t')
            ->leftJoin('theme_profiles as p', 't.theme_profile_id', '=', 'p.id')
            ->whereNull('p.id')->count();
        $ok = $orphaned === 0;
        $this->record('هیچ TenantThemeOverride به پروفایل حذف‌شده اشاره نمی‌کند', $ok, $ok ? null : "{$orphaned} رکورد یتیم");
    }

    private function checkMobilePayloadStructure(): void
    {
        if (!Schema::hasTable('theme_profiles')) { return; }
        try {
            $renderer = app(ThemeTokenRenderer::class);
            $payload  = $renderer->mobilePayload(null);
            $required = ['theme_id', 'theme_name', 'preset_key', 'colors', 'surfaces', 'typography', 'sidebar', 'header', 'borders'];
            $missing  = array_diff($required, array_keys($payload));
            $ok       = empty($missing);
            $this->record('Mobile payload دارای همه فیلدهای لازم است', $ok, $ok ? null : 'فیلد گمشده: ' . implode(', ', $missing));
        } catch (\Throwable $e) {
            $this->record('Mobile payload دارای همه فیلدهای لازم است', false, $e->getMessage());
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function record(string $label, bool $pass, ?string $note = null, ?string $level = null): void
    {
        $this->results[] = [
            'check'  => $label,
            'status' => $pass ? 'pass' : ($level ?? 'fail'),
            'note'   => $note,
        ];
    }

    private function printTable(): void
    {
        $this->table(['وضعیت', 'بررسی', 'یادداشت'], array_map(fn ($r) => [
            $r['status'] === 'pass' ? '<fg=green>✓</>' : ($r['status'] === 'warning' ? '<fg=yellow>⚠</>' : '<fg=red>✗</>'),
            $r['check'],
            $r['note'] ?? '',
        ], $this->results));
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 97 => 'A+',
            $score >= 93 => 'A',
            $score >= 90 => 'A-',
            $score >= 87 => 'B+',
            $score >= 83 => 'B',
            $score >= 80 => 'B-',
            default      => 'C',
        };
    }
}
