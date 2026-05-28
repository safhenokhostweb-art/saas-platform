<?php

namespace SaaSPlatform\CoreUtils\Frontend;

/**
 * Phase 10.0 — Canonical Component Registry
 * Tracks all registered core-ui components and their source directories.
 * Used for drift detection and governance validation.
 */
class CanonicalComponentRegistry
{
    private array $registered = [];

    public function register(string $component, string $sourcePath, string $module = 'core-utils'): void
    {
        $this->registered[$component] = [
            'component'   => $component,
            'path'        => $sourcePath,
            'module'      => $module,
            'canonical'   => $module === 'core-utils',
            'registered'  => now()->toIso8601String(),
        ];
    }

    public function discover(): void
    {
        // Auto-discover from core-utils
        $coreDir = realpath(__DIR__ . '/../../resources/views/components');
        if ($coreDir && is_dir($coreDir)) {
            foreach (glob($coreDir . '/*.blade.php') as $file) {
                $name = basename($file, '.blade.php');
                $this->register($name, $file, 'core-utils');
            }
        }

        // Auto-discover from forms module
        $formsDir = base_path('packages/saas-platform/modules/forms/resources/views/core-ui/components');
        if (is_dir($formsDir)) {
            foreach (glob($formsDir . '/*.blade.php') as $file) {
                $name = basename($file, '.blade.php');
                if (! isset($this->registered[$name])) {
                    $this->register($name, $file, 'forms');
                }
            }
        }
    }

    public function all(): array
    {
        return $this->registered;
    }

    public function canonical(): array
    {
        return array_filter($this->registered, fn($c) => $c['canonical']);
    }

    public function nonCanonical(): array
    {
        return array_filter($this->registered, fn($c) => ! $c['canonical']);
    }

    public function has(string $component): bool
    {
        return isset($this->registered[$component]);
    }

    public function conflicts(): array
    {
        $seen = [];
        $conflicts = [];
        foreach ($this->registered as $name => $data) {
            if (isset($seen[$name])) {
                $conflicts[$name] = [$seen[$name], $data];
            }
            $seen[$name] = $data;
        }
        return $conflicts;
    }

    public function report(): array
    {
        $this->discover();
        return [
            'total'         => count($this->registered),
            'canonical'     => count($this->canonical()),
            'non_canonical' => count($this->nonCanonical()),
            'conflicts'     => $this->conflicts(),
            'components'    => $this->registered,
        ];
    }
}
