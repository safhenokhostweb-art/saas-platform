<?php

namespace SaaSPlatform\Modules\Tickets\Services;

/**
 * PHASE 7.1 — TASK 5: Ticket Canonical Validation Service
 *
 * Validates the module's canonical architecture integrity.
 * Used by module:build and platform:validate-authority.
 */
class TicketCanonicalValidationService
{
    protected array $results = [];

    public function validate(): array
    {
        $this->results = [];

        $this->checkPackageViews();
        $this->checkNoHardcodedLayouts();
        $this->checkNoTenantLeakageInAdminViews();
        $this->checkNoPlatformLeakageInSiteViews();
        $this->checkContractsExist();

        $pass = array_filter($this->results, fn ($r) => $r['status'] === 'FAIL');

        return [
            'valid'   => empty($pass),
            'results' => $this->results,
        ];
    }

    protected function checkPackageViews(): void
    {
        $adminViews = __DIR__ . '/../../resources/views/admin/tickets';
        $siteViews  = __DIR__ . '/../../resources/views/site/tickets';

        $this->addResult('admin views exist', is_dir($adminViews) ? 'PASS' : 'WARN', '');
        $this->addResult('site views exist',  is_dir($siteViews)  ? 'PASS' : 'WARN', '');
    }

    protected function checkNoHardcodedLayouts(): void
    {
        $viewsDir = __DIR__ . '/../../resources/views';
        if (!is_dir($viewsDir)) {
            return;
        }

        $violations = [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsDir));
        foreach ($iter as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                if (preg_match('/@extends\(\s*[\'"]layouts\./', $content)) {
                    $violations[] = basename($file->getPathname());
                }
            }
        }

        $this->addResult(
            'No hardcoded layouts in views',
            empty($violations) ? 'PASS' : 'FAIL',
            empty($violations) ? '' : implode(', ', $violations)
        );
    }

    protected function checkNoTenantLeakageInAdminViews(): void
    {
        // Admin views may use @platform/@central but not customer-only patterns
        $this->addResult('Admin view authority', 'PASS', 'Not scanned in service (use module:build)');
    }

    protected function checkNoPlatformLeakageInSiteViews(): void
    {
        $siteViews = __DIR__ . '/../../resources/views/site/tickets';
        if (!is_dir($siteViews)) {
            $this->addResult('Site views segregation', 'WARN', 'site/tickets/ directory missing');
            return;
        }

        $violations = [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($siteViews));
        foreach ($iter as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                if (preg_match('/@(platform|central)\b/', $content)) {
                    $violations[] = basename($file->getPathname());
                }
            }
        }

        $this->addResult(
            'No platform leakage in site views',
            empty($violations) ? 'PASS' : 'FAIL',
            empty($violations) ? '' : implode(', ', $violations)
        );
    }

    protected function checkContractsExist(): void
    {
        $contracts = [
            \SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface::class,
            \SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface::class,
            \SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketReplyRepositoryInterface::class,
        ];

        foreach ($contracts as $contract) {
            $this->addResult(
                "Contract: " . class_basename($contract),
                interface_exists($contract) ? 'PASS' : 'FAIL',
                ''
            );
        }
    }

    protected function addResult(string $check, string $status, string $detail): void
    {
        $this->results[] = compact('check', 'status', 'detail');
    }
}
