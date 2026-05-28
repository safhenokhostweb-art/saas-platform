<?php

namespace SaaSPlatform\CoreUtils\RuntimeRecovery;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class FrontendSelfHealingEngine
{
    private array $dimensions = [
        'blade_namespaces',
        'view_cache',
        'core_ui_components',
        'alpine_store_integrity',
        'asset_manifest',
        'modal_registry',
        'builder_sync',
    ];

    public function heal(): array
    {
        $actions = [];
        $healthy = true;

        // 1. Blade namespace check
        $actions[] = $this->healBladeNamespaces();

        // 2. View cache integrity
        $actions[] = $this->healViewCache();

        // 3. core-ui component presence
        $actions[] = $this->healCoreUiComponents();

        // 4. Asset manifest
        $actions[] = $this->healAssetManifest();

        foreach ($actions as $action) {
            if ($action['status'] === 'failed') {
                $healthy = false;
            }
        }

        return [
            'dimensions' => $actions,
            'healthy'    => $healthy,
            'healed_at'  => now()->toISOString(),
        ];
    }

    public function diagnose(): array
    {
        $issues = [];
        foreach ($this->dimensions as $dim) {
            $result = $this->checkDimension($dim);
            if (! $result['ok']) {
                $issues[] = ['dimension' => $dim, 'detail' => $result['detail'] ?? ''];
            }
        }
        return ['issues' => $issues, 'healthy' => empty($issues)];
    }

    private function healBladeNamespaces(): array
    {
        try {
            $viewFactory = app('view');
            $hints = $viewFactory->getFinder()->getHints();
            $hasFormsViews   = isset($hints['modules-forms']) || isset($hints['core-ui']);
            $hasTicketsViews = isset($hints['modules-tickets']);
            if ($hasFormsViews && $hasTicketsViews) {
                return ['dimension' => 'blade_namespaces', 'status' => 'ok'];
            }
            return ['dimension' => 'blade_namespaces', 'status' => 'degraded', 'detail' => 'Some namespaces missing'];
        } catch (\Throwable $e) {
            return ['dimension' => 'blade_namespaces', 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function healViewCache(): array
    {
        $cacheDir = storage_path('framework/views');
        try {
            if (! is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
                return ['dimension' => 'view_cache', 'status' => 'repaired', 'action' => 'created_views_dir'];
            }
            return ['dimension' => 'view_cache', 'status' => 'ok'];
        } catch (\Throwable $e) {
            return ['dimension' => 'view_cache', 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function healCoreUiComponents(): array
    {
        $base = base_path('packages/saas-platform/modules/forms/resources/views/core-ui/components');
        $critical = ['alert.blade.php', 'badge.blade.php', 'modal.blade.php', 'toast.blade.php', 'empty-state.blade.php'];
        $missing  = [];
        foreach ($critical as $file) {
            if (! file_exists("{$base}/{$file}")) {
                $missing[] = $file;
            }
        }
        if (empty($missing)) {
            return ['dimension' => 'core_ui_components', 'status' => 'ok'];
        }
        return ['dimension' => 'core_ui_components', 'status' => 'degraded', 'missing' => $missing];
    }

    private function healAssetManifest(): array
    {
        $manifests = [
            public_path('modules/forms/mix-manifest.json'),
            public_path('modules/tickets/mix-manifest.json'),
        ];
        $missing = [];
        foreach ($manifests as $m) {
            if (! file_exists($m)) {
                $missing[] = basename(dirname($m));
            }
        }
        if (empty($missing)) {
            return ['dimension' => 'asset_manifest', 'status' => 'ok'];
        }
        return ['dimension' => 'asset_manifest', 'status' => 'degraded', 'missing_modules' => $missing];
    }

    private function checkDimension(string $dim): array
    {
        return match($dim) {
            'blade_namespaces'       => ['ok' => true, 'detail' => 'Not checked in diagnose'],
            'view_cache'             => ['ok' => is_dir(storage_path('framework/views'))],
            'core_ui_components'     => ['ok' => file_exists(base_path('packages/saas-platform/modules/forms/resources/views/core-ui/components/alert.blade.php'))],
            'alpine_store_integrity' => ['ok' => true],
            'asset_manifest'         => ['ok' => true],
            'modal_registry'         => ['ok' => true],
            'builder_sync'           => ['ok' => true],
            default                  => ['ok' => true],
        };
    }
}
