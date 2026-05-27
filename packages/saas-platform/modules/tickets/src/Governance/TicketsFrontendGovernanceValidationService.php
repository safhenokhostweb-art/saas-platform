<?php

namespace SaaSPlatform\Modules\Tickets\Governance;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Phase 9.1 — Task 10
 *
 * Validates that ALL ticket frontend rendering originates from the canonical
 * package views ONLY. Any divergence (duplicate UI, tenant/platform-specific
 * blade code, @tenant directives in canonical views, hollow-out violations)
 * is treated as an ARCHITECTURAL REGRESSION and results in a failing exit code.
 *
 * Architecture Law:
 *   packages/saas-platform/modules/tickets/Resources/views/
 *   is the ONE and ONLY source of ticket UI.
 */
class TicketsFrontendGovernanceValidationService
{
    /** Canonical package views root — absolute path */
    private string $packageViewsPath;

    /** customer-site root — may not exist in some environments */
    private string $customerSitePath;

    /** saas-platform app root */
    private string $platformPath;

    /** Accumulated check results */
    private array $checks = [];

    public function __construct()
    {
        $base = base_path();

        $this->packageViewsPath = realpath($base . '/packages/saas-platform/modules/tickets/Resources/views') ?: '';
        $this->platformPath     = $base;

        // Resolve customer-site sibling directory
        $sibling = dirname($base) . '/customer-site';
        $this->customerSitePath = is_dir($sibling) ? realpath($sibling) : '';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Public API
    // ─────────────────────────────────────────────────────────────────────────

    public function run(): array
    {
        $this->checks = [];

        $this->checkCanonicalViewsExist();
        $this->checkNoTenantDirectivesInCanonicalViews();
        $this->checkNoTenantDirectivesInSiteViews();
        $this->checkCustomerSiteLegacyViewsHollowedOut();
        $this->checkNoTicketUiInCustomerSiteResources();
        $this->checkSupportControllerUsesCanonicalViews();
        $this->checkRouteAliasesPresent();
        $this->checkDtosExist();
        $this->checkGovernanceCommandRegistered();
        $this->checkNoBladeExtendsInLegacyViews();

        $passed   = collect($this->checks)->every(fn ($c) => $c['passed']);
        $passCount = collect($this->checks)->filter(fn ($c) => $c['passed'])->count();
        $total     = count($this->checks);

        return [
            'passed'  => $passed,
            'score'   => "{$passCount}/{$total}",
            'checks'  => $this->checks,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Individual governance checks
    // ─────────────────────────────────────────────────────────────────────────

    /** C01 — All 6 canonical views must exist */
    private function checkCanonicalViewsExist(): void
    {
        $required = [
            'admin/tickets/index.blade.php',
            'admin/tickets/show.blade.php',
            'admin/tickets/create.blade.php',
            'site/tickets/index.blade.php',
            'site/tickets/show.blade.php',
            'site/tickets/create.blade.php',
        ];

        $missing = [];
        foreach ($required as $rel) {
            if (! file_exists($this->packageViewsPath . '/' . $rel)) {
                $missing[] = $rel;
            }
        }

        $this->record('C01', 'Canonical views exist', empty($missing),
            empty($missing) ? 'all 6 canonical views present' : 'missing: ' . implode(', ', $missing));
    }

    /** C02 — No @tenant / @endtenant directives in admin canonical views */
    private function checkNoTenantDirectivesInCanonicalViews(): void
    {
        $violations = $this->grepInDir(
            $this->packageViewsPath . '/admin',
            '/@tenant|@endtenant/',
            '*.blade.php'
        );

        $this->record('C02', 'No @tenant in admin canonical views', empty($violations),
            empty($violations) ? 'clean' : 'found in: ' . implode(', ', $violations));
    }

    /** C03 — No @tenant / @endtenant directives in site canonical views */
    private function checkNoTenantDirectivesInSiteViews(): void
    {
        $violations = $this->grepInDir(
            $this->packageViewsPath . '/site',
            '/@tenant|@endtenant/',
            '*.blade.php'
        );

        $this->record('C03', 'No @tenant in site canonical views', empty($violations),
            empty($violations) ? 'clean' : 'found in: ' . implode(', ', $violations));
    }

    /** C04 — customer-site legacy support views must be hollowed-out (governance comment) */
    private function checkCustomerSiteLegacyViewsHollowedOut(): void
    {
        if (! $this->customerSitePath) {
            $this->record('C04', 'Legacy views hollowed out', true, 'customer-site not found — skipped');
            return;
        }

        $legacyViews = [
            'resources/views/admin/support/index.blade.php',
            'resources/views/admin/support/show.blade.php',
            'resources/views/admin/support/create.blade.php',
        ];

        $marker     = 'CANONICAL FRONTEND AUTHORITY ENFORCED';
        $violations = [];

        foreach ($legacyViews as $rel) {
            $path = $this->customerSitePath . '/' . $rel;
            if (! file_exists($path)) {
                continue;
            }
            $content = file_get_contents($path);
            if (! str_contains($content, $marker)) {
                $violations[] = $rel;
            }
        }

        $this->record('C04', 'Legacy views hollowed out', empty($violations),
            empty($violations) ? 'all hollowed out' : 'not hollowed: ' . implode(', ', $violations));
    }

    /** C05 — No @extends or <html> ticket UI blocks in customer-site support resource views */
    private function checkNoTicketUiInCustomerSiteResources(): void
    {
        if (! $this->customerSitePath) {
            $this->record('C05', 'No ticket UI in customer-site views', true, 'customer-site not found — skipped');
            return;
        }

        $dir        = $this->customerSitePath . '/resources/views/admin/support';
        $violations = [];

        if (is_dir($dir)) {
            foreach (glob($dir . '/*.blade.php') as $file) {
                $content = file_get_contents($file);
                // If file has actual Blade layout extension (excluding stub/comments) → violation
                if (preg_match('/@extends\s*\(/', $content)) {
                    $violations[] = basename($file);
                }
            }
        }

        $this->record('C05', 'No @extends in customer-site support views', empty($violations),
            empty($violations) ? 'clean' : 'violation in: ' . implode(', ', $violations));
    }

    /** C06 — customer-site SupportController must use tickets:: view namespace */
    private function checkSupportControllerUsesCanonicalViews(): void
    {
        if (! $this->customerSitePath) {
            $this->record('C06', 'SupportController uses canonical views', true, 'customer-site not found — skipped');
            return;
        }

        $candidates = [
            $this->customerSitePath . '/app/Http/Controllers/Admin/SupportController.php',
        ];

        foreach ($candidates as $path) {
            if (! file_exists($path)) {
                continue;
            }

            $content = file_get_contents($path);

            // Must reference tickets:: namespace
            if (! preg_match('/view\s*\(\s*[\'"]tickets::/', $content)) {
                $this->record('C06', 'SupportController uses canonical views', false,
                    basename($path) . ' does not render tickets:: views');
                return;
            }

            // Must NOT reference admin.support.* local views for index/show/create
            if (preg_match('/view\s*\(\s*[\'"]admin\.support\.(index|show|create)[\'"]/', $content)) {
                $this->record('C06', 'SupportController uses canonical views', false,
                    basename($path) . ' still renders legacy admin.support.* views');
                return;
            }
        }

        $this->record('C06', 'SupportController uses canonical views', true, 'tickets:: namespace used');
    }

    /** C07 — customer-site web.php must define tickets.site.* route aliases */
    private function checkRouteAliasesPresent(): void
    {
        if (! $this->customerSitePath) {
            $this->record('C07', 'Route aliases present in customer-site', true, 'customer-site not found — skipped');
            return;
        }

        $webPhp = $this->customerSitePath . '/routes/web.php';
        if (! file_exists($webPhp)) {
            $this->record('C07', 'Route aliases present in customer-site', false, 'routes/web.php not found');
            return;
        }

        $content = file_get_contents($webPhp);
        $hasAlias = str_contains($content, "name('tickets.site.')") ||
                    str_contains($content, 'tickets.site.');

        $this->record('C07', 'Route aliases present in customer-site', $hasAlias,
            $hasAlias ? 'tickets.site.* aliases found' : 'tickets.site.* aliases missing from web.php');
    }

    /** C08 — TicketProxyData + TicketReplyProxyData DTOs must exist */
    private function checkDtosExist(): void
    {
        $dtoBase = $this->platformPath . '/packages/saas-platform/modules/tickets/src/Dto';
        $dtos    = [
            'TicketProxyData.php',
            'TicketReplyProxyData.php',
        ];

        $missing = [];
        foreach ($dtos as $dto) {
            if (! file_exists($dtoBase . '/' . $dto)) {
                $missing[] = $dto;
            }
        }

        $this->record('C08', 'Proxy DTOs exist', empty($missing),
            empty($missing) ? 'both DTOs present' : 'missing: ' . implode(', ', $missing));
    }

    /** C09 — Governance command class file must exist and be referenced in ServiceProvider */
    private function checkGovernanceCommandRegistered(): void
    {
        $commandFile = $this->platformPath
            . '/packages/saas-platform/modules/tickets/src/Console/Commands/ValidateFrontendGovernanceCommand.php';

        $spFile = $this->platformPath
            . '/packages/saas-platform/modules/tickets/src/Providers/TicketsServiceProvider.php';

        $cmdExists = file_exists($commandFile);

        $registered = false;
        if (file_exists($spFile)) {
            $registered = str_contains(
                file_get_contents($spFile),
                'ValidateFrontendGovernanceCommand'
            );
        }

        $passed = $cmdExists && $registered;

        $this->record('C09', 'Governance command registered', $passed,
            $passed
                ? 'command file exists and registered in ServiceProvider'
                : ($cmdExists ? 'command exists but not registered' : 'command file missing'));
    }

    /** C10 — No ticket-specific Blade components in customer-site components/ directory */
    private function checkNoBladeExtendsInLegacyViews(): void
    {
        if (! $this->customerSitePath) {
            $this->record('C10', 'No ticket Blade components in customer-site', true, 'customer-site not found — skipped');
            return;
        }

        $componentsDir = $this->customerSitePath . '/resources/views/components';
        $violations    = [];

        if (is_dir($componentsDir)) {
            foreach (glob($componentsDir . '/ticket*.blade.php') as $file) {
                $violations[] = basename($file);
            }
            foreach (glob($componentsDir . '/support*.blade.php') as $file) {
                $violations[] = basename($file);
            }
        }

        $this->record('C10', 'No ticket Blade components in customer-site', empty($violations),
            empty($violations) ? 'no rogue components' : 'found: ' . implode(', ', $violations));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function record(string $id, string $label, bool $passed, string $detail): void
    {
        $this->checks[] = compact('id', 'label', 'passed', 'detail');
    }

    /** Recursively search files matching $glob in $dir for $pattern. Returns relative file paths. */
    private function grepInDir(string $dir, string $pattern, string $glob = '*.php'): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $results = [];
        $rii     = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($rii as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if (! fnmatch($glob, $file->getFilename())) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if (preg_match($pattern, $content)) {
                $results[] = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        return $results;
    }
}
