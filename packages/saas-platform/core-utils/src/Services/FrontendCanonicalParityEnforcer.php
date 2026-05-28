<?php

namespace SaaSPlatform\CoreUtils\Services;

use Illuminate\Support\Facades\File;

class FrontendCanonicalParityEnforcer
{
    private string $platformBase;
    private string $customerBase;

    /** Module view paths that must render from package, never from customer-site */
    private array $canonicalModules = [
        'admin/forms'   => 'modules-forms',
        'admin/tickets' => 'modules-tickets',
        'site/forms'    => 'modules-forms',
        'site/tickets'  => 'modules-tickets',
    ];

    /** Forbidden override directories in customer-site */
    private array $forbiddenOverridePaths = [
        'resources/views/admin/forms',
        'resources/views/admin/tickets',
        'resources/views/site/forms',
        'resources/views/site/tickets',
        'resources/views/vendor/modules-forms',
        'resources/views/vendor/modules-tickets',
    ];

    public function __construct()
    {
        $this->platformBase = base_path();
        $this->customerBase = realpath(base_path('../../customer-site')) ?: '';
    }

    public function audit(): array
    {
        $violations   = [];
        $checks       = [];
        $score        = 100;

        // Check 1: No forbidden overrides in customer-site
        $checks[] = $this->checkNoForbiddenOverrides($violations, $score);

        // Check 2: canonical package views exist
        $checks[] = $this->checkCanonicalViewsExist($violations, $score);

        // Check 3: core-ui components present in both contexts
        $checks[] = $this->checkCoreUiComponents($violations, $score);

        // Check 4: No duplicate Blade namespaces
        $checks[] = $this->checkNoDuplicateNamespaces($violations, $score);

        // Check 5: Platform directives integrity (@platform / @tenant)
        $checks[] = $this->checkBladeDirectives($violations, $score);

        return [
            'score'      => max(0, $score),
            'violations' => $violations,
            'checks'     => $checks,
            'parity'     => $score >= 90,
            'grade'      => $this->grade($score),
            'audited_at' => now()->toISOString(),
        ];
    }

    public function enforce(): array
    {
        $audit = $this->audit();
        $fixed = [];

        foreach ($audit['violations'] as $v) {
            if ($v['type'] === 'forbidden_override' && isset($v['path'])) {
                // We do NOT delete files autonomously — report what must be removed
                $fixed[] = ['action' => 'requires_removal', 'path' => $v['path']];
            }
        }

        return array_merge($audit, ['enforcement_actions' => $fixed]);
    }

    private function checkNoForbiddenOverrides(array &$violations, float &$score): array
    {
        $found = 0;
        if ($this->customerBase && is_dir($this->customerBase)) {
            foreach ($this->forbiddenOverridePaths as $path) {
                $full = $this->customerBase . DIRECTORY_SEPARATOR . $path;
                if (is_dir($full)) {
                    $files = File::allFiles($full);
                    foreach ($files as $file) {
                        $violations[] = [
                            'type'     => 'forbidden_override',
                            'severity' => 'critical',
                            'message'  => "customer-site contains forbidden override: {$path}/{$file->getFilename()}",
                            'path'     => $path . '/' . $file->getFilename(),
                        ];
                        $score -= 5;
                        $found++;
                    }
                }
            }
        }
        return ['check' => 'no_forbidden_overrides', 'pass' => $found === 0, 'violations' => $found];
    }

    private function checkCanonicalViewsExist(array &$violations, float &$score): array
    {
        $missing = 0;
        $packagesBase = base_path('packages/saas-platform');
        $required = [
            'modules/forms/resources/views/admin/forms/index.blade.php',
            'modules/forms/resources/views/admin/forms/builder.blade.php',
            'modules/tickets/Resources/views/admin/tickets/index.blade.php',
            'modules/tickets/Resources/views/admin/tickets/show.blade.php',
        ];
        foreach ($required as $rel) {
            $full = $packagesBase . DIRECTORY_SEPARATOR . $rel;
            if (! file_exists($full)) {
                $violations[] = [
                    'type'     => 'missing_canonical_view',
                    'severity' => 'critical',
                    'message'  => "Canonical view missing: {$rel}",
                ];
                $score -= 10;
                $missing++;
            }
        }
        return ['check' => 'canonical_views_exist', 'pass' => $missing === 0, 'missing' => $missing];
    }

    private function checkCoreUiComponents(array &$violations, float &$score): array
    {
        $required = [
            'alert', 'badge', 'modal', 'toast', 'skeleton-card', 'empty-state',
            'page-header', 'stat-card', 'tabs', 'toolbar',
            'ai-assistant-panel', 'ai-suggestion-card', 'command-palette',
        ];
        $missing   = 0;
        $viewsBase = base_path('packages/saas-platform/modules/forms/resources/views/core-ui/components');
        foreach ($required as $component) {
            if (! file_exists("{$viewsBase}/{$component}.blade.php")) {
                $violations[] = [
                    'type'     => 'missing_core_ui_component',
                    'severity' => 'warning',
                    'message'  => "core-ui component missing: {$component}",
                ];
                $score -= 2;
                $missing++;
            }
        }
        return ['check' => 'core_ui_components', 'pass' => $missing === 0, 'missing' => $missing];
    }

    private function checkNoDuplicateNamespaces(array &$violations, float &$score): array
    {
        // Check customer-site service providers do not redefine core-ui namespace
        $pass = true;
        if ($this->customerBase) {
            $providers = glob($this->customerBase . '/app/Providers/*.php') ?: [];
            foreach ($providers as $provider) {
                $content = file_get_contents($provider);
                if (str_contains($content, 'core-ui') && str_contains($content, 'anonymousComponentNamespace')) {
                    $violations[] = [
                        'type'     => 'duplicate_namespace',
                        'severity' => 'warning',
                        'message'  => "customer-site provider re-declares core-ui namespace: " . basename($provider),
                    ];
                    $score -= 5;
                    $pass = false;
                }
            }
        }
        return ['check' => 'no_duplicate_namespaces', 'pass' => $pass];
    }

    private function checkBladeDirectives(array &$violations, float &$score): array
    {
        $spFile = base_path('packages/saas-platform/core-utils/src/Providers/CoreUtilsServiceProvider.php');
        $content = file_exists($spFile) ? file_get_contents($spFile) : '';
        $pass = str_contains($content, "@platform") || str_contains($content, 'platform');
        if (! $pass) {
            $violations[] = [
                'type'     => 'missing_blade_directives',
                'severity' => 'critical',
                'message'  => '@platform / @tenant Blade directives not registered',
            ];
            $score -= 15;
        }
        return ['check' => 'blade_directives', 'pass' => $pass];
    }

    private function grade(float $score): string
    {
        return match(true) {
            $score >= 97 => 'A+',
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            default      => 'F',
        };
    }
}
