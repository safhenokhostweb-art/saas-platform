<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Contracts\Container\Container;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteRepositoryInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\AuthUserResolverInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleLicenseServiceInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\DateFormatterInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\CaptchaServiceInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormRepositoryInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormServiceInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormSiteServiceInterface;

class FormsIsolationValidationService
{
    private array $checks = [];

    public function __construct(private Container $app) {}

    public function run(): array
    {
        $this->checks = [];

        $this->checkContractBinding(TenantContextContract::class,        'C01', 'TenantContextContract bound');
        $this->checkContractBinding(SiteRepositoryInterface::class,      'C02', 'SiteRepositoryInterface bound');
        $this->checkContractBinding(AuthUserResolverInterface::class,    'C03', 'AuthUserResolverInterface bound');
        $this->checkContractBinding(ModuleLicenseServiceInterface::class,'C04', 'ModuleLicenseServiceInterface bound');
        $this->checkContractBinding(DateFormatterInterface::class,       'C05', 'DateFormatterInterface bound');
        $this->checkContractBinding(ActivityLoggerInterface::class,      'C06', 'ActivityLoggerInterface bound');
        $this->checkContractBinding(CaptchaServiceInterface::class,      'C07', 'CaptchaServiceInterface bound');
        $this->checkContractBinding(FormRepositoryInterface::class,      'C08', 'FormRepositoryInterface bound');
        $this->checkContractBinding(FormServiceInterface::class,         'C09', 'FormServiceInterface bound');
        $this->checkContractBinding(FormSiteServiceInterface::class,     'C10', 'FormSiteServiceInterface bound');

        $this->checkContractResolvable(TenantContextContract::class,        'R01', 'TenantContextContract resolvable');
        $this->checkContractResolvable(SiteRepositoryInterface::class,      'R02', 'SiteRepositoryInterface resolvable');
        $this->checkContractResolvable(ModuleLicenseServiceInterface::class,'R03', 'ModuleLicenseServiceInterface resolvable');
        $this->checkContractResolvable(DateFormatterInterface::class,       'R04', 'DateFormatterInterface resolvable');
        $this->checkContractResolvable(CaptchaServiceInterface::class,      'R05', 'CaptchaServiceInterface resolvable');

        $this->checkConfigExists('F01', 'forms config exists');
        $this->checkNoAppImports('S01', 'no App\\ imports in package PHP files');

        $passed  = count(array_filter($this->checks, fn($c) => $c['passed']));
        $total   = count($this->checks);
        $allPass = ($passed === $total);

        return [
            'module'  => 'forms',
            'passed'  => $allPass,
            'score'   => "{$passed}/{$total}",
            'checks'  => $this->checks,
        ];
    }

    private function checkContractBinding(string $abstract, string $id, string $label): void
    {
        $bound  = $this->app->bound($abstract);
        $this->checks[] = [
            'id'     => $id,
            'label'  => $label,
            'passed' => $bound,
            'detail' => $bound ? 'OK' : "NOT BOUND: {$abstract}",
        ];
    }

    private function checkContractResolvable(string $abstract, string $id, string $label): void
    {
        try {
            $instance = $this->app->make($abstract);
            $this->checks[] = [
                'id'     => $id,
                'label'  => $label,
                'passed' => true,
                'detail' => 'OK (' . class_basename($instance) . ')',
            ];
        } catch (\Throwable $e) {
            $this->checks[] = [
                'id'     => $id,
                'label'  => $label,
                'passed' => false,
                'detail' => 'FAILED: ' . $e->getMessage(),
            ];
        }
    }

    private function checkConfigExists(string $id, string $label): void
    {
        $exists = config('forms') !== null;
        $this->checks[] = [
            'id'     => $id,
            'label'  => $label,
            'passed' => $exists,
            'detail' => $exists ? 'OK' : 'forms config not loaded',
        ];
    }

    private function checkNoAppImports(string $id, string $label): void
    {
        $packagePath = realpath(__DIR__ . '/../../');
        $violations  = [];

        if ($packagePath && is_dir($packagePath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($packagePath, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = str_replace($packagePath . DIRECTORY_SEPARATOR, '', $file->getPathname());

                // Skip vendor/compiled files
                if (str_contains($relativePath, 'vendor' . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                // Skip config files — they may reference App\ as publish-time defaults
                // that the consuming app overrides after running vendor:publish.
                if (str_starts_with($relativePath, 'config' . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                // Skip validation/safety tooling files: they contain 'App\' in string
                // literals, regex patterns, and comments for inspection purposes —
                // not as actual PHP namespace imports.
                $basename = basename($relativePath);
                if (in_array($basename, [
                    'FormsIsolationValidationService.php',
                    'FormsFrontendGovernanceValidationService.php',
                    'FormsRuntimeSafetyService.php',
                    'ValidateFormsIsolationCommand.php',
                ], true)) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                // Match "use App\" or "new \App\" or "App\Models" etc.
                if (preg_match('/\buse\s+App\\\\/', $contents) || preg_match('/[^a-zA-Z]App\\\\(?!Adapters)/', $contents)) {
                    $violations[] = $relativePath;
                }
            }
        }

        $passed = count($violations) === 0;
        $this->checks[] = [
            'id'     => $id,
            'label'  => $label,
            'passed' => $passed,
            'detail' => $passed ? 'OK' : implode(', ', $violations),
        ];
    }
}
