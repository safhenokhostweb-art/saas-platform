<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerInstallationPackage;
use App\Models\CustomerRemoteServer;
use App\Models\Infrastructure\DnsProviderSetting;
use App\Services\CustomerInstallation\Dns\DnsPreflightResult;
use App\Services\CustomerInstallation\Dns\DomainDnsPreflight;
use App\Services\CustomerInstallation\Dns\ServerPublicIpResolver;
use App\Services\CustomerInstallation\Enums\DriverType;

/**
 * Pre-dispatch checks shared by every path that starts a fresh customer
 * installation (the onboarding wizard, ADR-088, and the legacy
 * customer-deployment start endpoint). Extracted unchanged from
 * CustomerDeploymentController::start() so the two paths cannot drift.
 */
class InstallationTargetGuard
{
    public function __construct(
        private readonly DomainDnsPreflight $dnsPreflight,
        private readonly ServerPublicIpResolver $serverIps,
    ) {}

    /**
     * Persian error when $domain cannot reach $server (not registered, only a
     * wildcard match, or, with SSL, pointing elsewhere), else null. This is
     * the submit-time copy of the pipeline's VALIDATE_DOMAIN_DNS stage.
     */
    public function dnsPreflightError(string $domain, CustomerRemoteServer $server, bool $sslEnabled): ?string
    {
        return $this->dnsPreflight($domain, $server, $sslEnabled)?->error;
    }

    /**
     * The full submit-time result, or null when the decision is left to the
     * pipeline (a platform subdomain on a server without a known public IP).
     */
    public function dnsPreflight(string $domain, CustomerRemoteServer $server, bool $sslEnabled): ?DnsPreflightResult
    {
        $serverIp = $this->serverIps->forServer($server);

        // Without SSH only the server record's host is known. When it is not
        // a public address, VALIDATE_DOMAIN_DNS can still find one bound on
        // the server, so a platform subdomain is decided there, not here.
        if ($serverIp === null && $this->dnsPreflight->isPlatformManaged(strtolower($domain))) {
            return null;
        }

        return $this->dnsPreflight->check($domain, $serverIp, $sslEnabled);
    }

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

    /** Persian error when active package is missing or stale, else null. */
    public function packageError(): ?string
    {
        $pkg = CustomerInstallationPackage::active();
        if ($pkg === null) {
            return 'هیچ بسته نصب فعالی برای استقرار سایت مشتری ثبت نشده است.';
        }
        if ($pkg->isStale()) {
            return "نسخه بسته نصب فعال ({$pkg->version}) قدیمی است. حداقل نسخه مورد نیاز " . CustomerInstallationPackage::MINIMUM_SUPPORTED_VERSION . " است.";
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

    /**
     * Persian error when a custom domain has no DNS record of its own yet
     * (none at all, or only a wildcard answer), else null. Server-independent,
     * for the wizard's live check; dnsPreflightError() is the full check.
     */
    public function customDomainDnsError(string $domain): ?string
    {
        return $this->customDomainDnsCheck($domain)?->error;
    }

    /** Server-independent live check of a custom domain; null for a platform subdomain. */
    public function customDomainDnsCheck(string $domain): ?DnsPreflightResult
    {
        $domain = preg_replace('#^https?://#i', '', rtrim($domain, '/'));
        if ($this->isPlatformSubdomain($domain)) {
            return null;
        }

        return $this->dnsPreflight->check($domain, null, false);
    }
}
