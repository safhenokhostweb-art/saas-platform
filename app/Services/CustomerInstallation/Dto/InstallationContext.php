<?php

namespace App\Services\CustomerInstallation\Dto;

use App\Models\CustomerInstallation;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;

class InstallationContext
{
    public array $stageOutputs = [];
    public array $rollbackStack = [];

    public function __construct(
        public readonly CustomerInstallation      $installation,
        public readonly CustomerInstallRequest    $request,
        public RemoteConnectionInterface          $connection,
        public readonly ?ModuleUpdateTarget       $moduleTarget = null,
    ) {}

    public function pushRollback(string $stage, array $snapshot): void
    {
        $this->rollbackStack[] = ['stage' => $stage, 'snapshot' => $snapshot];
    }

    public function popRollback(): ?array
    {
        return array_pop($this->rollbackStack);
    }

    public function recordOutput(string $stage, array $output): void
    {
        $this->stageOutputs[$stage] = $output;
    }

    public function getOutput(string $stage): array
    {
        return $this->stageOutputs[$stage] ?? [];
    }

    public function customerSlug(): string
    {
        // Strip non-ASCII bytes first so Persian/Arabic company names don't produce
        // an all-underscore slug. The installation ID suffix guarantees uniqueness.
        $ascii   = preg_replace('/[^\x20-\x7E]/', '', $this->request->company);
        $clean   = strtolower(preg_replace('/[^a-z0-9]+/', '_', $ascii));
        $clean   = trim($clean, '_');
        $segment = ($clean === '') ? 'site' : substr($clean, 0, 20);

        return 'cust_' . $segment . '_' . $this->installation->id;
    }

    public function webRoot(): string
    {
        return '/var/www/' . $this->customerSlug();
    }

    /**
     * Remote path of the target module's own directory within the site's
     * web root — never the web root itself. Only valid when moduleTarget
     * is set (MODE_MODULE_UPDATE runs).
     */
    public function moduleInstallPath(): string
    {
        if (!$this->moduleTarget) {
            throw new \RuntimeException('moduleInstallPath() called without a moduleTarget on the context.');
        }
        return $this->webRoot() . '/packages/saas-platform/modules/' . $this->moduleTarget->moduleSlug;
    }
}
