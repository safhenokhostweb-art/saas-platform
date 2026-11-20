<?php

namespace App\Services\Domains;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\PlatformLicense;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Services\ActivityLogger;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Dns\DomainDnsPreflight;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Env\RemoteEnvFile;
use App\Services\CustomerInstallation\RemoteConnectionService;
use App\Services\CustomerInstallation\Ssl\NginxVhostWriter;
use App\Services\CustomerInstallation\Ssl\VpsSslProvisioner;
use App\Services\CustomerInstallation\Stages\IssueSslStage;
use App\Services\CustomerInstallation\Stages\ValidateDomainDnsStage;
use App\Services\Onboarding\OnboardingDomainPolicy;
use App\Services\Onboarding\SiteTemplateBinder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 — runs on the queue for the site domain panel. Re-uses the
 * pipeline's own VALIDATE_DOMAIN_DNS, the shared NginxVhostWriter and
 * ISSUE_SSL against the site's completed installation, with the domain
 * swapped in.
 *
 * ssl:      the primary domain (connect a site built in the awaiting-DNS
 *           state, add www, renew).
 * activate: make a pending custom domain the site's domain. The current
 *           domain stays canonical until the new certificate and vhost are
 *           in place, so a failure at any step leaves the live site as it
 *           was; afterwards the old domain 301-redirects to the new one.
 */
class SiteDomainProvisioner
{
    public function __construct(
        private readonly SiteDomainService $domains,
        private readonly RemoteConnectionService $connections,
        private readonly ValidateDomainDnsStage $validateStage,
        private readonly IssueSslStage $sslStage,
        private readonly NginxVhostWriter $writer,
        private readonly VpsSslProvisioner $vpsSsl,
        private readonly DomainDnsPreflight $preflight,
        private readonly SiteTemplateBinder $binder,
    ) {}

    public function run(SiteDomain $row, string $action): void
    {
        $site = $row->site;
        $installation = $this->domains->installationFor($site);
        $server = $installation ? CustomerRemoteServer::find($installation->remote_server_id) : null;
        if ($installation === null || $server === null) {
            $this->fail($row, SiteDomainService::NO_INSTALLATION);

            return;
        }

        $connection = $this->connections->buildDriver($server);
        try {
            $connection->connect();
        } catch (\Throwable $e) {
            $this->fail($row, 'اتصال به سرور سایت ممکن نشد: ' . $e->getMessage());

            return;
        }

        try {
            $action === SiteDomainService::ACTION_ACTIVATE
                ? $this->activate($row, $site, $installation, $connection)
                : $this->issue($row, $site, $installation, $connection);
        } catch (\Throwable $e) {
            Log::error('SiteDomain: provisioning failed', ['site_domain_id' => $row->id, 'action' => $action, 'error' => $e->getMessage()]);
            $this->fail($row, 'خطای پیش‌بینی‌نشده: ' . $e->getMessage());
        } finally {
            try {
                $connection->disconnect();
            } catch (\Throwable) {
            }
        }
    }

    private function issue(SiteDomain $row, Site $site, CustomerInstallation $installation, DeploymentDriverInterface $connection): void
    {
        $ctx = $this->context($installation, $connection, $row->domain, $this->withWww($this->aliases($site)));

        if (!$this->dnsReady($row, $ctx)) {
            return;
        }

        // Serve www (and old domains) on port 80 first: certbot validates
        // every name through it, and sites installed before www support
        // have a vhost without them.
        if ($connection->capabilities()->supportsWebServerConfiguration) {
            $web = $this->writer->write($ctx);
            if (!$web->success) {
                $this->fail($row, 'به‌روزرسانی پیکربندی Nginx ناموفق بود: ' . $web->error);

                return;
            }
        }

        $ssl = $this->sslStage->execute($ctx);
        if (!$ssl->success) {
            $this->fail($row, (string) $ssl->error);

            return;
        }

        if (!$this->issued($ssl->output)) {
            $row->update(['status' => SiteDomain::STATUS_CONNECTED, 'last_error' => $ssl->output['message'] ?? 'گواهی SSL صادر نشد.']);

            return;
        }

        $this->markActive($row, $ctx, $ssl->output);
        $this->binder->dispatchIfPending($installation);
    }

    private function activate(SiteDomain $row, Site $site, CustomerInstallation $installation, DeploymentDriverInterface $connection): void
    {
        if (!$connection->capabilities()->supportsWebServerConfiguration) {
            $this->fail($row, 'تغییر دامنهٔ سایت فقط روی سرورهایی ممکن است که پلتفرم پیکربندی Nginx آن‌ها را مدیریت می‌کند (VPS). روی هاست cPanel دامنه را در خود پنل هاست اضافه کنید.');

            return;
        }

        $current = OnboardingDomainPolicy::normalizeHost((string) $site->domain);
        $target = $row->domain;
        $aliases = $this->aliases($site);
        $ctx = $this->context($installation, $connection, $target, []);

        if (!$this->dnsReady($row, $ctx)) {
            return;
        }

        // 1. The current domain stays canonical; the new names are answered
        //    (and redirected to it) so certbot can validate them.
        $staging = $this->context($installation, $connection, $current, $this->withWww([$target, ...$aliases]));
        $web = $this->writer->write($staging);
        if (!$web->success) {
            $this->fail($row, 'آماده‌سازی Nginx برای دامنهٔ جدید ناموفق بود: ' . $web->error);

            return;
        }

        // 2. The licence must accept the new host before it becomes canonical.
        $this->allowLicenseDomain($site, $target);

        // 3. Certificate for the new domain; ISSUE_SSL then rewrites the
        //    vhost with it canonical and the old names redirecting.
        $ctx->vhostAliases = array_values(array_diff($this->withWww([$current, ...$aliases]), [$target]));
        $ssl = $this->sslStage->execute($ctx);
        if (!$ssl->success || !$this->issued($ssl->output)) {
            // The staging vhost (current domain canonical) is still in place.
            $this->fail($row, (string) ($ssl->error ?? $ssl->output['message'] ?? 'گواهی SSL دامنهٔ جدید صادر نشد.'));

            return;
        }
        if (empty($ssl->output['web_server_reconfigured'])) {
            $this->fail($row, 'گواهی SSL دامنهٔ جدید صادر شد، اما Nginx به دامنهٔ جدید سوئیچ نشد؛ دامنهٔ فعلی بدون تغییر فعال است. دوباره تلاش کنید.');

            return;
        }

        // 4. The application now answers on the new domain.
        $envPath = $ctx->webRoot() . '/.env';
        $env = $connection->execute(RemoteEnvFile::set($envPath, 'APP_URL', 'https://' . $target));
        $connection->execute('cd ' . escapeshellarg($ctx->webRoot()) . ' && php artisan config:clear 2>&1; php artisan cache:clear 2>&1');

        DB::transaction(function () use ($row, $site, $current, $target) {
            SiteDomain::query()->where('site_id', $site->id)->where('role', SiteDomain::ROLE_PRIMARY)
                ->update(['role' => SiteDomain::ROLE_ALIAS, 'status' => SiteDomain::STATUS_REDIRECT]);
            $row->update(['role' => SiteDomain::ROLE_PRIMARY]);
            $site->update(['domain' => $target]);

            // A site whose old domain never had a row still keeps it as a redirect.
            SiteDomain::query()->firstOrCreate(['domain' => $current], [
                'site_id' => $site->id, 'role' => SiteDomain::ROLE_ALIAS, 'status' => SiteDomain::STATUS_REDIRECT,
            ]);
        });

        $this->markActive($row->fresh(), $ctx, $ssl->output);
        if (!str_contains((string) $env->stdout, RemoteEnvFile::SET_OK)) {
            $row->update(['last_error' => 'دامنه فعال شد، اما APP_URL در .env سایت به‌روزرسانی نشد؛ آن را دستی روی https://' . $target . ' بگذارید.']);
        }

        ActivityLogger::log('site.domain_changed', "دامنهٔ سایت «{$site->title}» از {$current} به {$target} تغییر کرد", $site, [
            'site_id' => $site->id, 'from' => $current, 'to' => $target,
        ]);

        $this->binder->dispatchIfPending($installation);
    }

    /** VALIDATE_DOMAIN_DNS in strict mode; false (and the row back to awaiting DNS) when it fails. */
    private function dnsReady(SiteDomain $row, InstallationContext $ctx): bool
    {
        $dns = $this->validateStage->execute($ctx);
        if ($dns->success) {
            return true;
        }

        $row->update([
            'status'         => SiteDomain::STATUS_AWAITING_DNS,
            'last_error'     => $dns->error,
            'dns_checked_at' => now(),
        ]);

        return false;
    }

    /** @param array<string, mixed> $sslOutput */
    private function markActive(SiteDomain $row, InstallationContext $ctx, array $sslOutput): void
    {
        $expires = null;
        if (($sslOutput['ssl_managed_by'] ?? null) !== 'arvan_cdn') {
            $expires = $this->vpsSsl->checkCertificate($ctx, $row->domain)->expiresAt;
        }

        $row->update([
            'status'             => SiteDomain::STATUS_ACTIVE,
            'www_in_certificate' => in_array('www.' . $row->domain, (array) ($sslOutput['certificate_names'] ?? []), true),
            'ssl_expires_at'     => $expires,
            'last_error'         => null,
            'last_job_at'        => now(),
        ]);
    }

    /** @param array<string, mixed> $output */
    private function issued(array $output): bool
    {
        return ($output['status'] ?? null) === 'issued' || ($output['ssl_managed_by'] ?? null) === 'arvan_cdn';
    }

    private function allowLicenseDomain(Site $site, string $domain): void
    {
        PlatformLicense::query()->where('site_id', $site->id)->whereNull('revoked_at')->get()
            ->each(function (PlatformLicense $license) use ($domain) {
                $allowed = array_values(array_filter(array_map('trim', explode(',', (string) $license->domain_allowed))));
                if ($allowed === [] || in_array($domain, array_map('strtolower', $allowed), true)) {
                    return; // empty = any domain
                }
                $license->update(['domain_allowed' => implode(',', [...$allowed, $domain])]);
            });
    }

    /** @return list<string> previous domains of the site, kept as redirects */
    private function aliases(Site $site): array
    {
        return SiteDomain::query()->where('site_id', $site->id)->where('role', SiteDomain::ROLE_ALIAS)->pluck('domain')->all();
    }

    /**
     * @param list<string> $domains
     * @return list<string> the domains plus their www names
     */
    private function withWww(array $domains): array
    {
        $names = [];
        foreach ($domains as $domain) {
            $names[] = $domain;
            if (($www = $this->preflight->wwwAlias($domain)) !== null) {
                $names[] = $www;
            }
        }

        return array_values(array_unique($names));
    }

    /** @param list<string> $vhostAliases */
    private function context(CustomerInstallation $installation, DeploymentDriverInterface $connection, string $domain, array $vhostAliases): InstallationContext
    {
        $request = CustomerInstallRequest::fromArray(array_merge($installation->request_payload ?? [], [
            'domain'      => $domain,
            'ssl_enabled' => true,
            'dns_pending' => false,
        ]));
        $ctx = new InstallationContext($installation, $request, $connection);
        $ctx->vhostAliases = array_values(array_diff($vhostAliases, [$domain, $this->preflight->wwwAlias($domain)]));

        return $ctx;
    }

    private function fail(SiteDomain $row, string $message): void
    {
        $row->update(['status' => SiteDomain::STATUS_FAILED, 'last_error' => $message, 'last_job_at' => now()]);
    }
}
