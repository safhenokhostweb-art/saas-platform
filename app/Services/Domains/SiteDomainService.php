<?php

namespace App\Services\Domains;

use App\Jobs\SiteDomainJob;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerRemoteServer;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Services\CustomerInstallation\Dns\DomainDnsPreflight;
use App\Services\CustomerInstallation\Dns\ServerPublicIpResolver;
use App\Services\Onboarding\OnboardingDomainPolicy;
use Illuminate\Validation\ValidationException;

/**
 * Phase 3 — a site's domains after installation: records the state the
 * pipeline left (awaiting DNS, active SSL...), checks DNS on demand, queues
 * the connection/SSL job (SiteDomainProvisioner) and holds a new custom
 * domain while it is being connected.
 */
class SiteDomainService
{
    public const ACTION_SSL = 'ssl';
    public const ACTION_ACTIVATE = 'activate';

    public function __construct(
        private readonly DomainDnsPreflight $preflight,
        private readonly ServerPublicIpResolver $serverIps,
        private readonly OnboardingDomainPolicy $policy,
    ) {}

    /** The completed fresh installation whose server and web root host the site. */
    public function installationFor(Site $site): ?CustomerInstallation
    {
        $complete = CustomerInstallation::query()->where('site_id', $site->id)->where('status', 'complete');

        return (clone $complete)->where('install_mode', 'MODE_FRESH')->latest('id')->first()
            ?? $complete->latest('id')->first();
    }

    public function serverIp(Site $site): ?string
    {
        $installation = $this->installationFor($site);

        return $installation ? $this->serverIps->forServer(CustomerRemoteServer::find($installation->remote_server_id)) : null;
    }

    /** The row for sites.domain, created from the installation record when missing. */
    public function primary(Site $site): SiteDomain
    {
        $domain = OnboardingDomainPolicy::normalizeHost((string) $site->domain);
        $row = SiteDomain::query()->where('domain', $domain)->first();
        if ($row !== null) {
            return $row;
        }

        $installation = $this->installationFor($site);

        return ($installation ? $this->recordFromInstallation($installation) : null)
            ?? SiteDomain::query()->create([
                'site_id' => $site->id, 'domain' => $domain,
                'role' => SiteDomain::ROLE_PRIMARY, 'status' => SiteDomain::STATUS_CONNECTED,
            ]);
    }

    /**
     * Record the DNS/SSL state a completed installation left for its site.
     * Called once the pipeline has run (CustomerInstallationPipelineJob).
     */
    public function recordFromInstallation(CustomerInstallation $installation): ?SiteDomain
    {
        if (!$installation->isComplete() || !$installation->site_id || ($site = Site::find($installation->site_id)) === null) {
            return null;
        }

        $domain = OnboardingDomainPolicy::normalizeHost((string) $site->domain);
        $validate = $this->stageOutput($installation, 'VALIDATE_DOMAIN_DNS');
        $ssl = $this->stageOutput($installation, 'ISSUE_SSL');
        $sslStatus = $ssl['status'] ?? null;

        [$status, $error] = match (true) {
            $sslStatus === 'awaiting_dns' || (!empty($validate['awaiting_dns']) && $sslStatus !== 'issued')
                => [SiteDomain::STATUS_AWAITING_DNS, $ssl['message'] ?? $validate['warning'] ?? null],
            $sslStatus === 'issued' || ($ssl['ssl_managed_by'] ?? null) === 'arvan_cdn'
                => [SiteDomain::STATUS_ACTIVE, null],
            in_array($sslStatus, ['ssl_failed', 'failed'], true)
                => [SiteDomain::STATUS_FAILED, $ssl['message'] ?? null],
            default
                => [SiteDomain::STATUS_CONNECTED, $ssl['message'] ?? null],
        };

        return SiteDomain::query()->updateOrCreate(['domain' => $domain], [
            'site_id'            => $site->id,
            'role'               => SiteDomain::ROLE_PRIMARY,
            'status'             => $status,
            'www_in_certificate' => in_array('www.' . $domain, (array) ($ssl['certificate_names'] ?? []), true),
            'dns_report'         => $validate !== [] ? ['installation' => $validate] : null,
            'last_error'         => $error,
        ]);
    }

    /** A theme push needs the live domain; hold it while DNS is pending. */
    public function holdsTemplatePush(CustomerInstallation $installation): bool
    {
        if (!$installation->site_id || ($site = Site::find($installation->site_id)) === null) {
            return false;
        }

        return SiteDomain::query()
            ->where('domain', OnboardingDomainPolicy::normalizeHost((string) $site->domain))
            ->where('status', SiteDomain::STATUS_AWAITING_DNS)
            ->exists();
    }

    /**
     * Check DNS for the domain and its www name against the site's server.
     *
     * @return array{server_ip: ?string, checked_at: string, records: list<array{host: string, name: string, ok: bool, code: string, message: ?string, resolved: list<string>}>}
     */
    public function checkDns(SiteDomain $row): array
    {
        $serverIp = $this->serverIp($row->site);
        $records = [];
        foreach (array_filter([$row->domain, $this->preflight->wwwAlias($row->domain)]) as $host) {
            $check = $this->preflight->check($host, $serverIp, true);
            $records[] = [
                'host'     => $host,
                'name'     => $host === $row->domain ? '@' : 'www',
                'ok'       => $check->ok && in_array($check->code, [DomainDnsPreflight::PASS_POINTS_HERE, DomainDnsPreflight::PASS_MANAGED], true),
                'code'     => $check->code,
                'message'  => $check->error,
                'resolved' => $check->lookup->ips,
            ];
        }

        $report = ['server_ip' => $serverIp, 'checked_at' => now()->toIso8601String(), 'records' => $records];
        $update = ['dns_report' => $report, 'dns_checked_at' => now()];

        // Only the waiting states move here; active/issuing/redirect belong
        // to the provisioning job.
        if (in_array($row->status, [SiteDomain::STATUS_AWAITING_DNS, SiteDomain::STATUS_CONNECTED, SiteDomain::STATUS_FAILED], true)) {
            $apexOk = $records[0]['ok'] ?? false;
            if ($apexOk && $row->status === SiteDomain::STATUS_AWAITING_DNS) {
                $update['status'] = SiteDomain::STATUS_CONNECTED;
                $update['last_error'] = null;
            } elseif (!$apexOk && $row->status !== SiteDomain::STATUS_FAILED) {
                $update['status'] = SiteDomain::STATUS_AWAITING_DNS;
                $update['last_error'] = $records[0]['message'] ?? null;
            }
        }

        $row->update($update);

        return $report;
    }

    /** Register a custom domain to replace the site's domain once connected. */
    public function addPending(Site $site, string $input): SiteDomain
    {
        $check = $this->policy->check(OnboardingDomainPolicy::MODE_CUSTOM, $input, withDns: false);
        if (!$check['ok']) {
            throw ValidationException::withMessages(['domain' => $check['error']]);
        }
        $domain = (string) $check['domain'];

        if ($domain === OnboardingDomainPolicy::normalizeHost((string) $site->domain)) {
            throw ValidationException::withMessages(['domain' => 'این دامنه همین حالا دامنهٔ اصلی سایت است.']);
        }
        if ($check['platform_subdomain']) {
            throw ValidationException::withMessages(['domain' => 'این بخش برای دامنهٔ اختصاصی مشتری است؛ زیردامنهٔ پلتفرم را از اینجا نمی‌توان افزود.']);
        }
        if (SiteDomain::query()->where('domain', $domain)->exists()) {
            throw ValidationException::withMessages(['domain' => "دامنهٔ «{$domain}» قبلاً برای یک سایت ثبت شده است."]);
        }
        if ($this->installationFor($site) === null) {
            throw ValidationException::withMessages(['domain' => self::NO_INSTALLATION]);
        }

        SiteDomain::query()->where('site_id', $site->id)->where('role', SiteDomain::ROLE_PENDING)->delete();

        $row = SiteDomain::query()->create([
            'site_id' => $site->id, 'domain' => $domain,
            'role' => SiteDomain::ROLE_PENDING, 'status' => SiteDomain::STATUS_AWAITING_DNS,
        ]);
        $this->checkDns($row);

        return $row->fresh();
    }

    public function removePending(SiteDomain $row): void
    {
        if (!$row->isPending() || $row->status === SiteDomain::STATUS_ISSUING) {
            throw ValidationException::withMessages(['domain' => 'فقط دامنهٔ در انتظاری که در حال اتصال نیست حذف می‌شود.']);
        }
        $row->delete();
    }

    /** Queue SiteDomainProvisioner: ssl (primary) or activate (pending). */
    public function queue(SiteDomain $row, string $action): void
    {
        $expected = $action === self::ACTION_ACTIVATE ? $row->isPending() : $row->isPrimary();
        if (!$expected || !in_array($action, [self::ACTION_SSL, self::ACTION_ACTIVATE], true)) {
            throw ValidationException::withMessages(['domain' => 'این عملیات برای این دامنه مجاز نیست.']);
        }
        if ($row->status === SiteDomain::STATUS_ISSUING) {
            throw ValidationException::withMessages(['domain' => 'برای این دامنه یک عملیات در حال اجراست؛ چند لحظه بعد صفحه را تازه کنید.']);
        }
        if ($this->installationFor($row->site) === null) {
            throw ValidationException::withMessages(['domain' => self::NO_INSTALLATION]);
        }

        $row->update(['status' => SiteDomain::STATUS_ISSUING, 'last_error' => null, 'last_job_at' => now()]);

        SiteDomainJob::dispatch($row->id, $action)
            ->onConnection('customer-install')
            ->onQueue('customer-install');
    }

    public const NO_INSTALLATION = 'این سایت نصب خودکار کامل‌شده‌ای ندارد؛ اتصال دامنه و صدور SSL فقط برای سایت‌هایی ممکن است که پلتفرم آن‌ها را روی سرور نصب کرده است.';

    /** @return array<string, mixed> */
    private function stageOutput(CustomerInstallation $installation, string $stage): array
    {
        $output = CustomerInstallationLog::query()
            ->where('installation_id', $installation->id)
            ->where('stage', $stage)
            ->where('event', 'completed')
            ->latest('id')
            ->value('output');

        if (is_string($output)) {
            $output = json_decode($output, true);
        }

        return is_array($output) ? $output : [];
    }
}
