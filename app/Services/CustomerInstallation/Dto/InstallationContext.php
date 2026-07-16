<?php

namespace App\Services\CustomerInstallation\Dto;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\WebRootResolver;

class InstallationContext
{
    public array $stageOutputs = [];
    public array $rollbackStack = [];
    private ?string $resolvedWebRoot = null;

    /**
     * Phase 14-E — widened from RemoteConnectionInterface to
     * DeploymentDriverInterface so stages/the orchestrator can call
     * ->capabilities() for the capability gate. Every driver DriverFactory
     * ever constructs already implements the full DeploymentDriverInterface
     * (SshRemoteDriver, CpanelDeploymentDriver, FakeRemoteDriver), so this is
     * a safe narrowing, not a behavior change.
     */
    public function __construct(
        public readonly CustomerInstallation      $installation,
        public readonly CustomerInstallRequest    $request,
        public DeploymentDriverInterface          $connection,
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

    /**
     * Phase 14-F1 — delegates to WebRootResolver so this stays hosting-type
     * agnostic (no Stage, and now not even this DTO, branches on driver
     * type itself). Memoized: the underlying CustomerRemoteServer lookup
     * only needs to happen once per pipeline run, not once per Stage that
     * calls webRoot() (there are ~19 call sites).
     */
    public function webRoot(): string
    {
        if ($this->resolvedWebRoot === null) {
            $server = CustomerRemoteServer::find($this->installation->remote_server_id);
            // Phase 15-D — resolveForInstallation() (not resolve()) so a
            // real cPanel document root from ProvisionDomainStage is
            // preferred over the hosting-type default guess. See
            // WebRootResolver's docblock for the full priority order.
            $this->resolvedWebRoot = (new WebRootResolver())->resolveForInstallation($this, $this->customerSlug(), $server);
        }

        return $this->resolvedWebRoot;
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
