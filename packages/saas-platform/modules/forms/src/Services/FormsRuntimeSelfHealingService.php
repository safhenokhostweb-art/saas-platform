<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class FormsRuntimeSelfHealingService
{
    private const CACHE_TTL = 300; // 5 minutes

    public function runFullDiagnostic(): array
    {
        return [
            'blade_namespace'    => $this->healBladeNamespace(),
            'view_files'         => $this->healMissingViews(),
            'config'             => $this->healConfig(),
            'component_files'    => $this->healMissingComponents(),
            'cache'              => $this->healStaleCache(),
            'assets'             => $this->healBrokenAssets(),
            'directives'         => $this->healDirectives(),
        ];
    }

    public function isHealthy(): bool
    {
        $results = $this->runFullDiagnostic();
        return !in_array('fail', array_column($results, 'status'));
    }

    private function healBladeNamespace(): array
    {
        try {
            // Verify the core-ui anonymous component namespace is registered
            $viewFactory = app('view');
            $finder = $viewFactory->getFinder();

            // Try to find a known component
            $testView = 'forms::core-ui.components.toast';
            try {
                $finder->find($testView);
                return ['status' => 'pass', 'message' => 'Blade namespace core-ui:: resolved correctly', 'action' => 'none'];
            } catch (\InvalidArgumentException $e) {
                // Attempt recovery: re-register namespace
                \Illuminate\Support\Facades\Blade::anonymousComponentNamespace('forms::core-ui.components', 'core-ui');
                Log::warning('[Forms] Blade namespace core-ui:: was missing — re-registered', ['module' => 'forms']);
                return ['status' => 'recovered', 'message' => 'core-ui:: namespace re-registered', 'action' => 'reregistered'];
            }
        } catch (\Throwable $e) {
            return ['status' => 'warning', 'message' => 'Could not verify Blade namespace: ' . $e->getMessage(), 'action' => 'none'];
        }
    }

    private function healMissingViews(): array
    {
        $requiredViews = [
            'admin/forms/index.blade.php',
            'admin/forms/create.blade.php',
            'admin/forms/builder.blade.php',
            'admin/forms/submissions/index.blade.php',
            'admin/forms/analytics.blade.php',
            'admin/forms/automation/index.blade.php',
            'admin/forms/templates/index.blade.php',
            'site/forms/show.blade.php',
        ];

        $viewsBase = realpath(__DIR__ . '/../../resources/views');
        $missing = [];

        foreach ($requiredViews as $view) {
            if (!$viewsBase || !File::exists($viewsBase . '/' . $view)) {
                $missing[] = $view;
            }
        }

        if (!empty($missing)) {
            Log::error('[Forms] Missing canonical view files detected', ['missing' => $missing, 'module' => 'forms']);
            return [
                'status'  => 'fail',
                'message' => 'Missing view files: ' . implode(', ', $missing),
                'action'  => 'manual_intervention_required',
                'items'   => $missing,
            ];
        }

        return ['status' => 'pass', 'message' => 'All canonical views present', 'action' => 'none'];
    }

    private function healConfig(): array
    {
        $requiredKeys = [
            'forms.layouts.admin',
            'forms.layouts.site',
            'forms.field_types',
            'forms.pagination',
        ];

        $missing = array_filter($requiredKeys, fn($k) => config($k) === null);

        if (!empty($missing)) {
            // Attempt to re-merge config
            try {
                $configPath = realpath(__DIR__ . '/../../config/forms.php');
                if ($configPath && File::exists($configPath)) {
                    $merged = require $configPath;
                    foreach ($merged as $k => $v) {
                        if (config("forms.$k") === null) {
                            config(["forms.$k" => $v]);
                        }
                    }
                    Log::warning('[Forms] Config keys were missing — re-merged', ['module' => 'forms']);
                    return ['status' => 'recovered', 'message' => 'Missing config keys re-merged', 'action' => 'config_remerged'];
                }
            } catch (\Throwable $e) {
                // Fallback
            }

            return [
                'status'  => 'warning',
                'message' => 'Missing config keys: ' . implode(', ', $missing),
                'action'  => 'config_check_needed',
            ];
        }

        return ['status' => 'pass', 'message' => 'All required config keys present', 'action' => 'none'];
    }

    private function healMissingComponents(): array
    {
        $requiredComponents = [
            'core-ui/components/toast.blade.php',
            'core-ui/components/empty-state.blade.php',
            'core-ui/components/form-status-pill.blade.php',
            'core-ui/components/autosave-indicator.blade.php',
            'core-ui/components/analytics-card.blade.php',
            'core-ui/components/section-header.blade.php',
            'core-ui/components/skeleton-card.blade.php',
        ];

        $viewsBase = realpath(__DIR__ . '/../../resources/views');
        $missing = [];

        foreach ($requiredComponents as $component) {
            if (!$viewsBase || !File::exists($viewsBase . '/' . $component)) {
                $missing[] = $component;
            }
        }

        if (!empty($missing)) {
            Log::error('[Forms] Missing core-ui components', ['missing' => $missing, 'module' => 'forms']);
            return [
                'status'  => 'fail',
                'message' => 'Missing core-ui components: ' . implode(', ', $missing),
                'action'  => 'manual_intervention_required',
            ];
        }

        return ['status' => 'pass', 'message' => 'All core-ui components present', 'action' => 'none'];
    }

    private function healStaleCache(): array
    {
        $cacheKeys = [
            'forms_governance_score',
            'forms_parity_score',
            'forms_accessibility_score',
        ];

        $stale = [];
        foreach ($cacheKeys as $key) {
            $meta = Cache::get($key . '_meta');
            if ($meta && isset($meta['written_at'])) {
                $age = time() - $meta['written_at'];
                if ($age > 3600) {
                    Cache::forget($key);
                    $stale[] = $key;
                }
            }
        }

        if (!empty($stale)) {
            Log::info('[Forms] Stale cache entries cleared', ['keys' => $stale, 'module' => 'forms']);
            return ['status' => 'recovered', 'message' => 'Stale cache cleared: ' . implode(', ', $stale), 'action' => 'cache_cleared'];
        }

        return ['status' => 'pass', 'message' => 'Cache state is healthy', 'action' => 'none'];
    }

    private function healBrokenAssets(): array
    {
        $issues = [];

        // Check if public/build/manifest.json exists
        $manifest = public_path('build/manifest.json');
        if (!File::exists($manifest)) {
            $issues[] = 'Vite manifest missing at public/build/manifest.json';
        }

        if (!empty($issues)) {
            Log::warning('[Forms] Asset health issues detected', ['issues' => $issues, 'module' => 'forms']);
            return [
                'status'  => 'warning',
                'message' => 'Asset issues: ' . implode('; ', $issues),
                'action'  => 'run_npm_build',
                'items'   => $issues,
            ];
        }

        return ['status' => 'pass', 'message' => 'Asset pipeline healthy', 'action' => 'none'];
    }

    private function healDirectives(): array
    {
        // Verify Blade custom directives are registered
        $expected = ['platform', 'tenant', 'capability'];
        $customDirectives = [];

        try {
            $compiler = app('blade.compiler');
            $ref = new \ReflectionClass($compiler);
            $prop = $ref->getProperty('customDirectives');
            $prop->setAccessible(true);
            $customDirectives = array_keys($prop->getValue($compiler));
        } catch (\Throwable $e) {
            return ['status' => 'warning', 'message' => 'Could not inspect Blade directives: ' . $e->getMessage(), 'action' => 'none'];
        }

        $missing = array_diff($expected, $customDirectives);

        if (!empty($missing)) {
            Log::error('[Forms] Custom Blade directives missing', ['missing' => $missing, 'module' => 'forms']);
            return [
                'status'  => 'fail',
                'message' => 'Missing Blade directives: @' . implode(', @', $missing) . ' — FormsServiceProvider may not have booted',
                'action'  => 'check_service_provider',
            ];
        }

        return ['status' => 'pass', 'message' => 'All custom Blade directives registered', 'action' => 'none'];
    }
}
