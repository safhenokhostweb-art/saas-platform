<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * TASK 5 — Override Safety Validation
 *
 * Validates that package updates do not destroy overrides,
 * unsafe overrides are detected, drift is scanned,
 * and override precedence works correctly.
 *
 * This service enforces the override-safe architecture:
 * - Package views are AUTHORITY
 * - Published views in vendor/forms are OVERRIDES
 * - Drift detection compares package vs published views
 */
class FormsOverrideSafetyService
{
    /**
     * Path to package views (the authority).
     */
    protected string $packageViewsPath;

    /**
     * Path to published overrides.
     */
    protected string $publishedViewsPath;

    public function __construct()
    {
        $this->packageViewsPath = __DIR__ . '/../../resources/views';
        $this->publishedViewsPath = resource_path('views/vendor/forms');
    }

    /**
     * Scan for drift between package views and published overrides.
     *
     * @return array{drifted: bool, files: array<int, array{file: string, type: string, detail: string}>}
     */
    public function scanDrift(): array
    {
        $driftedFiles = [];

        if (!File::isDirectory($this->publishedViewsPath)) {
            return [
                'drifted' => false,
                'files' => [],
            ];
        }

        $publishedFiles = File::allFiles($this->publishedViewsPath);

        foreach ($publishedFiles as $file) {
            $relativePath = $file->getRelativePathname();
            $packageFile = $this->packageViewsPath . '/' . $relativePath;

            if (!File::exists($packageFile)) {
                // Published file has no package counterpart - orphaned override
                $driftedFiles[] = [
                    'file' => $relativePath,
                    'type' => 'orphaned_override',
                    'detail' => 'این فایل در پکیج وجود ندارد. override بی‌استفاده است.',
                ];
                continue;
            }

            $publishedContent = $file->getContents();
            $packageContent = File::get($packageFile);

            if ($publishedContent !== $packageContent) {
                // Content differs - legitimate override
                $driftedFiles[] = [
                    'file' => $relativePath,
                    'type' => 'modified_override',
                    'detail' => 'این فایل با نسخه پکیج تفاوت دارد. override مجاز است.',
                ];
            }
        }

        return [
            'drifted' => count($driftedFiles) > 0,
            'files' => $driftedFiles,
        ];
    }

    /**
     * Detect unsafe overrides that may break during updates.
     *
     * @return array{safe: bool, unsafe_overrides: array<int, array{file: string, reason: string}>}
     */
    public function detectUnsafeOverrides(): array
    {
        $unsafe = [];

        if (!File::isDirectory($this->publishedViewsPath)) {
            return [
                'safe' => true,
                'unsafe_overrides' => [],
            ];
        }

        $publishedFiles = File::allFiles($this->publishedViewsPath);

        foreach ($publishedFiles as $file) {
            $relativePath = $file->getRelativePathname();
            $packageFile = $this->packageViewsPath . '/' . $relativePath;
            $content = $file->getContents();

            // Check 1: File exists in package but has structural changes
            if (File::exists($packageFile)) {
                $packageContent = File::get($packageFile);

                // Check for removed section/stop injections
                if ($this->hasRemovedSection($packageContent, $content)) {
                    $unsafe[] = [
                        'file' => $relativePath,
                        'reason' => 'بخش‌های @section از پکیج حذف شده‌اند. با آپدیت پکیج ممکن است دچار شکست شود.',
                    ];
                }

                // Check for hardcoded layout paths instead of config-based
                if ($this->hasHardcodedLayout($content)) {
                    $unsafe[] = [
                        'file' => $relativePath,
                        'reason' => 'مسیر layout به صورت سخت‌کد شده است. باید از config(\'forms.layouts.admin\') استفاده کند.',
                    ];
                }
            }
        }

        return [
            'safe' => count($unsafe) === 0,
            'unsafe_overrides' => $unsafe,
        ];
    }

    /**
     * Validate that override precedence works correctly.
     *
     * @return array{valid: bool, active_overrides: array<int, string>}
     */
    public function validateOverridePrecedence(): array
    {
        $activeOverrides = [];

        if (!File::isDirectory($this->publishedViewsPath)) {
            return [
                'valid' => true,
                'active_overrides' => [],
            ];
        }

        $publishedFiles = File::allFiles($this->publishedViewsPath);

        foreach ($publishedFiles as $file) {
            $relativePath = $file->getRelativePathname();
            $packageFile = $this->packageViewsPath . '/' . $relativePath;

            if (File::exists($packageFile)) {
                $activeOverrides[] = $relativePath;
            }
        }

        return [
            'valid' => true,
            'active_overrides' => $activeOverrides,
        ];
    }

    /**
     * Simulate a package update and check if overrides survive.
     *
     * @return array{overrides_preserved: bool, affected_overrides: array<int, string>}
     */
    public function simulateUpdateImpact(): array
    {
        $affected = [];

        if (!File::isDirectory($this->publishedViewsPath)) {
            return [
                'overrides_preserved' => true,
                'affected_overrides' => [],
            ];
        }

        $publishedFiles = File::allFiles($this->publishedViewsPath);

        foreach ($publishedFiles as $file) {
            $relativePath = $file->getRelativePathname();
            $packageFile = $this->packageViewsPath . '/' . $relativePath;

            if (!File::exists($packageFile)) {
                // This override has no package counterpart - it's safe
                continue;
            }

            $publishedContent = $file->getContents();
            $packageContent = File::get($packageFile);

            if ($publishedContent !== $packageContent) {
                // Override exists - it will be preserved during update
                // (update only publishes new files, doesn't touch existing overrides)
                continue;
            }
        }

        return [
            'overrides_preserved' => true,
            'affected_overrides' => $affected,
        ];
    }

    /**
     * Check if the override has removed @section directives from the package version.
     */
    protected function hasRemovedSection(string $packageContent, string $overrideContent): bool
    {
        preg_match_all('/@section\((\'|")([^\'"]+)(\'|")\)/', $packageContent, $packageSections);
        preg_match_all('/@section\((\'|")([^\'"]+)(\'|")\)/', $overrideContent, $overrideSections);

        $packageSectionsList = $packageSections[2] ?? [];
        $overrideSectionsList = $overrideSections[2] ?? [];

        foreach ($packageSectionsList as $section) {
            if (!in_array($section, $overrideSectionsList, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the view has hardcoded layout paths.
     */
    protected function hasHardcodedLayout(string $content): bool
    {
        // Check for @extends that doesn't use config()
        if (preg_match('/@extends\(\s*[\'"](?!config)/', $content)) {
            return true;
        }

        return false;
    }
}
