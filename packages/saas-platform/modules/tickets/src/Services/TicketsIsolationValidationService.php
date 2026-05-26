<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use Illuminate\Contracts\Container\Container;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteRepositoryInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\AuthUserResolverInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleLicenseServiceInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\DateFormatterInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketReplyRepositoryInterface;

class TicketsIsolationValidationService
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
        $this->checkContractBinding(TicketServiceInterface::class,       'C07', 'TicketServiceInterface bound');
        $this->checkContractBinding(TicketRepositoryInterface::class,    'C08', 'TicketRepositoryInterface bound');
        $this->checkContractBinding(TicketReplyRepositoryInterface::class,'C09','TicketReplyRepositoryInterface bound');

        $this->checkContractResolvable(TenantContextContract::class,        'R01', 'TenantContextContract resolvable');
        $this->checkContractResolvable(SiteRepositoryInterface::class,      'R02', 'SiteRepositoryInterface resolvable');
        $this->checkContractResolvable(AuthUserResolverInterface::class,    'R03', 'AuthUserResolverInterface resolvable');
        $this->checkContractResolvable(TicketServiceInterface::class,       'R04', 'TicketServiceInterface resolvable');
        $this->checkContractResolvable(TicketRepositoryInterface::class,    'R05', 'TicketRepositoryInterface resolvable');

        $this->checkConfigExists('F01', 'tickets config exists');
        $this->checkNoAppImports('S01', 'no App\\ imports in package PHP files');

        $passed  = count(array_filter($this->checks, fn($c) => $c['passed']));
        $total   = count($this->checks);
        $allPass = ($passed === $total);

        return [
            'module'  => 'tickets',
            'passed'  => $allPass,
            'score'   => "{$passed}/{$total}",
            'checks'  => $this->checks,
        ];
    }

    private function checkContractBinding(string $abstract, string $id, string $label): void
    {
        $bound = $this->app->bound($abstract);
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
        $exists = config('tickets') !== null;
        $this->checks[] = [
            'id'     => $id,
            'label'  => $label,
            'passed' => $exists,
            'detail' => $exists ? 'OK' : 'tickets config not loaded',
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

                if (str_contains($relativePath, 'vendor' . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

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
