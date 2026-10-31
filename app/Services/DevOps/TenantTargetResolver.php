<?php

namespace App\Services\DevOps;

use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\Site;
use App\Services\CustomerInstallation\DriverFactory;
use App\Services\CustomerInstallation\WebRootResolver;
use App\Services\DevOps\Exceptions\TenantUnreachableException;

/**
 * Site → latest completed installation → remote server → driver + web root.
 *
 * Web root priority mirrors WebRootResolver::resolveForInstallation(): the
 * document root PROVISION_DOMAIN recorded for this installation, then the
 * hosting-type default from WebRootResolver::resolve().
 */
class TenantTargetResolver
{
    public function __construct(
        private readonly DriverFactory   $drivers,
        private readonly WebRootResolver $webRoots,
    ) {}

    /**
     * @throws TenantUnreachableException
     */
    public function resolve(Site $site): TenantTarget
    {
        $installation = CustomerInstallation::where('site_id', $site->id)
            ->where('status', 'complete')
            ->orderByDesc('id')
            ->first();

        if (!$installation) {
            throw new TenantUnreachableException('این سایت نصب کامل‌شده‌ای ندارد.');
        }

        $server = $installation->remoteServer;
        if (!$server) {
            throw new TenantUnreachableException('سرور میزبان این سایت ثبت نشده است.');
        }

        try {
            $driver = $this->drivers->buildForServer($server);
        } catch (\Throwable $e) {
            throw new TenantUnreachableException('نوع میزبانی این سایت از اجرای فرمان پشتیبانی نمی‌کند: ' . $e->getMessage(), 0, $e);
        }

        $webRoot = $this->provisionedDocumentRoot($installation)
            ?? $this->webRoots->resolve($driver, $this->slug($installation), $server);

        return new TenantTarget(
            site:           $site,
            driver:         $driver,
            webRoot:        rtrim($webRoot, '/'),
            installationId: $installation->id,
            serverId:       $server->id,
        );
    }

    private function provisionedDocumentRoot(CustomerInstallation $installation): ?string
    {
        $log = CustomerInstallationLog::where('installation_id', $installation->id)
            ->where('stage', 'PROVISION_DOMAIN')
            ->where('event', 'completed')
            ->first();

        $root = $log?->output['document_root'] ?? null;

        return is_string($root) && $root !== '' ? $root : null;
    }

    /** Same derivation as InstallationContext::slug() and DeleteContext::resolveSlug(). */
    private function slug(CustomerInstallation $installation): string
    {
        $ascii   = preg_replace('/[^\x20-\x7E]/', '', $installation->customer_company ?? '');
        $clean   = strtolower(preg_replace('/[^a-z0-9]+/', '_', $ascii));
        $clean   = trim($clean, '_');
        $segment = ($clean === '') ? 'site' : substr($clean, 0, 20);

        return 'cust_' . $segment . '_' . $installation->id;
    }
}
