<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FormsFrontendGovernanceValidationService
{
    private string $packageViewsPath;
    private array $violations = [];
    private array $warnings   = [];
    private array $passed     = [];

    public function __construct()
    {
        $this->packageViewsPath = realpath(
            __DIR__ . '/../../resources/views'
        ) ?: '';
    }

    public function validate(): array
    {
        $this->violations = [];
        $this->warnings   = [];
        $this->passed     = [];

        $this->checkNoDuplicateViewsInRootProject();
        $this->checkNoDuplicateViewsInCustomerSite();
        $this->checkConfigDrivenLayouts();
        $this->checkNoAppClassReferences();
        $this->checkPlatformTenantDirectivesUsed();
        $this->checkDarkModeReadiness();
        $this->checkAccessibility();
        $this->checkNoHardcodedCdnUrls();
        $this->checkResponsiveClasses();
        $this->checkCanonicalNamespace();

        return [
            'passed'     => $this->passed,
            'violations' => $this->violations,
            'warnings'   => $this->warnings,
            'score'      => $this->calculateScore(),
            'compliant'  => empty($this->violations),
        ];
    }

    private function checkNoDuplicateViewsInRootProject(): void
    {
        $rootLegacyPath = base_path('resources/views/admin/forms/_legacy');

        if (File::isDirectory($rootLegacyPath) && count(File::files($rootLegacyPath)) > 0) {
            $this->violations[] = [
                'rule'    => 'no_duplicate_views',
                'message' => "رویکرد تکراری: views/admin/forms/_legacy/ هنوز وجود دارد. باید حذف شود.",
                'path'    => $rootLegacyPath,
            ];
        } else {
            $this->passed[] = 'no_duplicate_views_root_legacy';
        }

        $rootSubmissionsPath = base_path('resources/views/admin/forms/submissions');
        if (File::isDirectory($rootSubmissionsPath) && count(File::files($rootSubmissionsPath)) > 0) {
            $this->violations[] = [
                'rule'    => 'no_duplicate_views',
                'message' => "دایرکتوری submissions تکراری در root project یافت شد.",
                'path'    => $rootSubmissionsPath,
            ];
        } else {
            $this->passed[] = 'no_duplicate_views_root_submissions';
        }
    }

    private function checkNoDuplicateViewsInCustomerSite(): void
    {
        $customerSitePath = base_path('../../customer-site/resources/views/admin/forms');

        if (File::isDirectory($customerSitePath)) {
            $files = File::allFiles($customerSitePath);
            $formModuleViews = array_filter($files, fn($f) => Str::endsWith($f->getFilename(), '.blade.php'));

            if (count($formModuleViews) > 0) {
                $this->violations[] = [
                    'rule'    => 'no_customer_site_duplicate_views',
                    'message' => "customer-site دارای views/admin/forms/ است که باید حذف شود.",
                    'path'    => $customerSitePath,
                ];
            } else {
                $this->passed[] = 'no_customer_site_duplicate_views';
            }
        } else {
            $this->passed[] = 'no_customer_site_duplicate_views';
        }
    }

    private function checkConfigDrivenLayouts(): void
    {
        $views = $this->getAllPackageViews();
        $violations = [];

        foreach ($views as $view) {
            $content = file_get_contents($view);
            if (str_contains($content, "@extends('layouts.admin')") || str_contains($content, '@extends("layouts.admin")')) {
                $violations[] = basename($view);
            }
            if (str_contains($content, "@extends('admin.layout')") || str_contains($content, '@extends("admin.layout")')) {
                $violations[] = basename($view);
            }
        }

        if (! empty($violations)) {
            $this->violations[] = [
                'rule'    => 'config_driven_layouts',
                'message' => 'Layout مستقیم هاردکد شده (باید از config("forms.layouts.*") استفاده شود): ' . implode(', ', $violations),
            ];
        } else {
            $this->passed[] = 'config_driven_layouts';
        }
    }

    private function checkNoAppClassReferences(): void
    {
        $views    = $this->getAllPackageViews();
        $phpFiles = $this->getAllPackagePhpFiles();
        $all      = array_merge($views, $phpFiles);

        $violations = [];

        foreach ($all as $file) {
            $content = file_get_contents($file);
            if (preg_match('/\\\\App\\\\/', $content)) {
                $relativePath = str_replace($this->packageViewsPath, '', $file);
                $violations[] = $relativePath;
            }
        }

        if (! empty($violations)) {
            $this->violations[] = [
                'rule'    => 'no_app_class_references',
                'message' => 'رفرنس App\\ در package views/src یافت شد: ' . implode(', ', array_slice($violations, 0, 5)),
            ];
        } else {
            $this->passed[] = 'no_app_class_references';
        }
    }

    private function checkPlatformTenantDirectivesUsed(): void
    {
        $adminViews = $this->getAdminPackageViews();
        $filesWithPlatformContent = 0;
        $filesUsingDirectives     = 0;

        foreach ($adminViews as $view) {
            $content = file_get_contents($view);
            if (str_contains($content, '@platform') || str_contains($content, 'isCentralAdmin') || str_contains($content, 'isTenantAdmin')) {
                continue;
            }
            if (str_contains($content, 'platform') || str_contains($content, 'site_id')) {
                $filesWithPlatformContent++;
            }
        }

        if ($filesWithPlatformContent > 3) {
            $this->warnings[] = [
                'rule'    => 'platform_tenant_directives',
                'message' => "{$filesWithPlatformContent} فایل admin view شاید نیاز به @platform/@tenant directive دارد.",
            ];
        } else {
            $this->passed[] = 'platform_tenant_directives';
        }
    }

    private function checkDarkModeReadiness(): void
    {
        $views = $this->getAllPackageViews();
        $hardcodedColors = 0;

        foreach ($views as $view) {
            $content = file_get_contents($view);
            if (preg_match_all('/style="[^"]*color:\s*#[0-9a-fA-F]{3,6}/', $content) > 2) {
                $hardcodedColors++;
            }
        }

        if ($hardcodedColors > 2) {
            $this->warnings[] = [
                'rule'    => 'dark_mode_readiness',
                'message' => "{$hardcodedColors} فایل view دارای رنگ‌های هاردکد شده است. برای dark mode باید از CSS custom properties استفاده شود.",
            ];
        } else {
            $this->passed[] = 'dark_mode_readiness';
        }
    }

    private function checkAccessibility(): void
    {
        $views = $this->getAllPackageViews();
        $missing = [];

        foreach ($views as $view) {
            $content = file_get_contents($view);
            if (str_contains($content, '<input') && ! str_contains($content, 'aria-') && ! str_contains($content, '<label')) {
                $missing[] = basename($view);
            }
        }

        if (! empty($missing)) {
            $this->warnings[] = [
                'rule'    => 'accessibility',
                'message' => 'فایل‌های زیر ممکن است label یا aria attribute نداشته باشند: ' . implode(', ', $missing),
            ];
        } else {
            $this->passed[] = 'accessibility';
        }
    }

    private function checkNoHardcodedCdnUrls(): void
    {
        $adminViews = $this->getAdminPackageViews();
        $cdnFiles   = [];

        foreach ($adminViews as $view) {
            $content = file_get_contents($view);
            if (preg_match('/https?:\/\/(cdn\.|unpkg\.|cdnjs\.)/', $content)) {
                $cdnFiles[] = basename($view);
            }
        }

        if (! empty($cdnFiles)) {
            $this->warnings[] = [
                'rule'    => 'no_hardcoded_cdn_urls',
                'message' => 'Admin views از CDN خارجی استفاده می‌کنند (ترجیحاً از asset() استفاده شود): ' . implode(', ', $cdnFiles),
            ];
        } else {
            $this->passed[] = 'no_hardcoded_cdn_urls';
        }
    }

    private function checkResponsiveClasses(): void
    {
        $views   = $this->getAllPackageViews();
        $missing = 0;

        foreach ($views as $view) {
            $content = file_get_contents($view);
            if (str_contains($content, 'grid') && ! preg_match('/\bmd:|sm:|lg:/', $content)) {
                $missing++;
            }
        }

        if ($missing > 2) {
            $this->warnings[] = [
                'rule'    => 'responsive_classes',
                'message' => "{$missing} فایل view ممکن است responsive Tailwind classes نداشته باشد.",
            ];
        } else {
            $this->passed[] = 'responsive_classes';
        }
    }

    private function checkCanonicalNamespace(): void
    {
        $views = $this->getAllPackageViews();
        $using = array_filter($views, fn($v) => str_contains(file_get_contents($v), "config('forms.layouts."));

        if (count($using) > 0) {
            $this->passed[] = 'canonical_namespace';
        } else {
            $this->warnings[] = [
                'rule'    => 'canonical_namespace',
                'message' => "هیچ view ای از config('forms.layouts.*') استفاده نمی‌کند.",
            ];
        }
    }

    private function calculateScore(): int
    {
        $total  = count($this->passed) + count($this->violations) + count($this->warnings);
        if ($total === 0) {
            return 100;
        }

        $weight = count($this->passed) + (count($this->warnings) * 0.7);
        return (int) min(100, round(($weight / $total) * 100));
    }

    private function getAllPackageViews(): array
    {
        if (! File::isDirectory($this->packageViewsPath)) {
            return [];
        }

        return array_map(
            fn($f) => $f->getPathname(),
            File::allFiles($this->packageViewsPath)
        );
    }

    private function getAdminPackageViews(): array
    {
        $adminPath = $this->packageViewsPath . '/admin';
        if (! File::isDirectory($adminPath)) {
            return [];
        }

        return array_map(
            fn($f) => $f->getPathname(),
            File::allFiles($adminPath)
        );
    }

    private function getAllPackagePhpFiles(): array
    {
        $srcPath = realpath(__DIR__ . '/../') ?: '';
        if (! File::isDirectory($srcPath)) {
            return [];
        }

        return array_map(
            fn($f) => $f->getPathname(),
            array_filter(File::allFiles($srcPath), fn($f) => $f->getExtension() === 'php')
        );
    }
}
