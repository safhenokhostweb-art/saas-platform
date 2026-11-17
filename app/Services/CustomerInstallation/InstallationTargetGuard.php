<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Models\Infrastructure\DnsProviderSetting;
use App\Services\CustomerInstallation\Enums\DriverType;

/**
 * Pre-dispatch checks shared by every path that starts a fresh customer
 * installation (the onboarding wizard, ADR-088, and the legacy
 * customer-deployment start endpoint). Extracted unchanged from
 * CustomerDeploymentController::start() so the two paths cannot drift.
 */
class InstallationTargetGuard
{
    /** Persian error when the server's driver cannot run an installation, else null. */
    public function driverError(CustomerRemoteServer $server): ?string
    {
        // Phase 14-B1 — a CustomerRemoteServer can exist with a driver type
        // that is registered but not yet implemented (cpanel/directadmin/
        // sftp/docker are architecture stubs). Without this check, starting
        // an installation against one dispatches a job that is guaranteed to
        // fail deep inside the pipeline (see Installation #44 root-cause
        // audit) instead of failing fast with a clear message here.
        $driverType = DriverType::tryFrom($server->type);
        if (!$driverType || !$driverType->isImplemented()) {
            return "درایور مقصد استقرار انتخاب‌شده («{$server->type}») هنوز پیاده‌سازی نشده و امکان شروع نصب روی آن وجود ندارد.";
        }

        return null;
    }

    /** The platform-managed root domain (e.g. safhenokhost.ir), or '' when none is configured. */
    public function platformRootDomain(): string
    {
        return strtolower(trim((string) (DnsProviderSetting::active()?->root_domain ?? '')));
    }

    /**
     * Platform-managed subdomains (under the configured root domain) need no
     * pre-existing DNS: ConfigureDnsStage creates their A record during the
     * pipeline.
     */
    public function isPlatformSubdomain(string $domain): bool
    {
        $root = $this->platformRootDomain();
        $domain = strtolower($domain);

        return $root !== '' && $domain !== $root && str_ends_with($domain, '.' . $root);
    }

    /** Persian error when a custom domain has no DNS record yet, else null. */
    public function customDomainDnsError(string $domain): ?string
    {
        $domain = preg_replace('#^https?://#i', '', rtrim($domain, '/'));
        if ($this->isPlatformSubdomain($domain)) {
            return null;
        }

        try {
            $dnsExists = checkdnsrr($domain, 'A')
                || checkdnsrr($domain, 'CNAME')
                || checkdnsrr($domain, 'AAAA');
        } catch (\Throwable) {
            $dnsExists = true; // fail-open if the DNS query itself fails
        }

        return $dnsExists
            ? null
            : "دامنه «{$domain}» در DNS یافت نشد. مطمئن شوید دامنه ثبت شده و رکورد DNS آن (A یا CNAME) فعال باشد.";
    }
}
