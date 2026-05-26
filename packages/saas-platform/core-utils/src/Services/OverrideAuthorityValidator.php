<?php

namespace SaaSPlatform\CoreUtils\Services;

use Illuminate\Support\Facades\File;

/**
 * PHASE 7.15 — TASK 7: Override Authority Validator
 *
 * Enforces the strict override policy:
 *
 * ALLOWED override paths:
 *   resources/views/vendor/forms/*
 *   resources/views/vendor/tickets/*
 *
 * ILLEGAL override paths (package views must NOT be duplicated here):
 *   resources/views/admin/forms/*
 *   resources/views/admin/tickets/*
 *   resources/views/site/forms/*
 *   resources/views/site/tickets/*
 *
 * Run via: php artisan platform:validate-ui-authority
 */
class OverrideAuthorityValidator
{
    /**
     * Paths that are illegal override locations.
     * If view files exist here for canonical modules, it's a violation.
     */
    protected const ILLEGAL_OVERRIDE_PATHS = [
        'resources/views/admin/forms',
        'resources/views/admin/tickets',
        'resources/views/admin/support-tickets',
        'resources/views/site/forms',
        'resources/views/site/tickets',
        'resources/views/customer/forms',
        'resources/views/customer/support-tickets',
    ];

    /**
     * Paths that are allowed for tenant view overrides.
     */
    protected const ALLOWED_OVERRIDE_PATHS = [
        'resources/views/vendor/forms',
        'resources/views/vendor/tickets',
    ];

    /**
     * Paths that are legacy (not illegal but need migration).
     */
    protected const LEGACY_PATHS = [
        'resources/views/admin/forms/_legacy',
        'resources/views/admin/support-tickets',
        'resources/views/customer/support-tickets',
        'resources/views/customer/forms',
    ];

    public function __construct(
        protected ?string $projectRoot = null
    ) {
        $this->projectRoot = $projectRoot ?? base_path();
    }

    /**
     * Run the full override authority validation.
     *
     * @return array{passed: bool, violations: array, warnings: array, info: array}
     */
    public function validate(): array
    {
        $violations = $this->detectIllegalOverrides();
        $warnings   = $this->detectStalePublishedViews();
        $info       = $this->detectLegacyViews();

        return [
            'passed'     => empty($violations),
            'violations' => $violations,
            'warnings'   => $warnings,
            'info'       => $info,
        ];
    }

    /**
     * Detect views in paths that are illegal override locations
     * for canonical module views (Forms, Tickets).
     */
    public function detectIllegalOverrides(): array
    {
        $violations = [];

        foreach (self::ILLEGAL_OVERRIDE_PATHS as $path) {
            $fullPath = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

            if (! File::isDirectory($fullPath)) {
                continue;
            }

            $files = File::allFiles($fullPath);

            // Files in _legacy subdirectory are not violations
            $activeFiles = array_filter($files, function ($file) {
                return ! str_contains($file->getPathname(), '_legacy');
            });

            if (! empty($activeFiles)) {
                foreach ($activeFiles as $file) {
                    $violations[] = [
                        'severity' => 'HIGH',
                        'path'     => $path . '/' . $file->getRelativePathname(),
                        'message'  => 'Illegal override: package canonical view must not be duplicated here.',
                        'action'   => 'Remove file and use package view namespace (e.g. tickets::admin.tickets.index)',
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * Detect views published to vendor/ that have drifted from the package source.
     * A stale published view is one that exists in vendor/ but differs from the package.
     */
    public function detectStalePublishedViews(): array
    {
        $warnings = [];

        $vendorPath = $this->projectRoot . '/resources/views/vendor';
        if (! File::isDirectory($vendorPath)) {
            return [];
        }

        $modules = ['forms', 'tickets'];

        foreach ($modules as $module) {
            $publishedPath = $vendorPath . '/' . $module;
            if (! File::isDirectory($publishedPath)) {
                continue;
            }

            $publishedFiles = File::allFiles($publishedPath);
            foreach ($publishedFiles as $file) {
                $warnings[] = [
                    'severity' => 'MEDIUM',
                    'path'     => 'resources/views/vendor/' . $module . '/' . $file->getRelativePathname(),
                    'message'  => 'Published override detected. Verify it is intentional and not stale.',
                    'action'   => 'Run drift:blades to check for divergence from package source.',
                ];
            }
        }

        return $warnings;
    }

    /**
     * Detect legacy view directories that need migration to _legacy/ or deletion.
     */
    public function detectLegacyViews(): array
    {
        $info = [];

        foreach (self::LEGACY_PATHS as $path) {
            $fullPath = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

            if (File::isDirectory($fullPath)) {
                $fileCount = count(File::allFiles($fullPath));
                $info[] = [
                    'severity' => 'LOW',
                    'path'     => $path,
                    'message'  => "Legacy view directory with {$fileCount} file(s). Move to _legacy/ after route audit.",
                    'action'   => 'Verify no active routes reference these views, then move to _legacy/.',
                ];
            }
        }

        return $info;
    }
}
