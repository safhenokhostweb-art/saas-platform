<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Contracts\DnsProviderInterface;
use App\Models\Infrastructure\DnsProviderSetting;
use App\Services\CustomerInstallation\Dns\DnsResolver;
use App\Services\CustomerInstallation\Dns\DomainDnsPreflight;
use App\Services\CustomerInstallation\Dns\ServerPublicIpResolver;
use App\Services\Dns\ArvanDnsProvider;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use Illuminate\Support\Facades\Log;

/**
 * Creates the platform subdomain's A record. Since 2026-11-17 this runs
 * right after PROVISION_DOMAIN, before any upload or database. The record
 * targets the server's inbound address (ServerPublicIpResolver), never its
 * outbound NAT address, and with SSL requested an unverified record fails
 * the stage at once instead of leaving it to ISSUE_SSL (installation 50).
 */
class ConfigureDnsStage extends AbstractStage
{
    /** @var \Closure(int): void */
    private \Closure $sleeper;

    public function __construct(
        private readonly ServerPublicIpResolver $serverIps,
        private readonly DnsResolver $dns,
        private readonly DomainDnsPreflight $preflight,
        ?\Closure $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? static function (int $seconds): void { sleep($seconds); };
    }

    public function name(): string { return 'CONFIGURE_DNS'; }
    public function label(): string { return 'ایجاد رکورد DNS'; }
    public function estimatedSeconds(): int { return 60; }
    public function canRollback(): bool { return true; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $setting = DnsProviderSetting::active();

            // If DNS provider is not configured, skip gracefully.
            // Installations on custom domains or without Arvan DNS still proceed.
            if (!$setting || !$setting->isReady()) {
                Log::info('CIE/DNS: No active DNS provider configured — skipping DNS stage.', [
                    'installation_id' => $ctx->installation->id,
                    'domain'          => $ctx->request->domain,
                ]);
                return StageResult::success($this->name(), [
                    'skipped' => true,
                    'reason'  => 'No DNS provider configured',
                ], [], $this->elapsed());
            }

            $fqdn       = $ctx->request->domain;            // e.g. tenant1.safhenokhost.ir
            $rootDomain = $setting->root_domain;             // e.g. safhenokhost.ir

            // Only auto-manage DNS for subdomains of the platform's root domain.
            // Custom-domain installations are managed by the customer's own DNS.
            if (!$this->isDomainUnderRoot($fqdn, $rootDomain)) {
                Log::info('CIE/DNS: Domain is not under platform root — skipping DNS stage.', [
                    'fqdn'        => $fqdn,
                    'root_domain' => $rootDomain,
                ]);
                return StageResult::success($this->name(), [
                    'skipped'     => true,
                    'reason'      => 'Domain is not a subdomain of ' . $rootDomain,
                    'fqdn'        => $fqdn,
                    'root_domain' => $rootDomain,
                ], [], $this->elapsed());
            }

            // Derive subdomain label (without the root domain suffix).
            $subdomain = rtrim(substr($fqdn, 0, -strlen('.' . $rootDomain)), '.');

            // The server's inbound public IP (never its outbound NAT address).
            $serverIp = $this->serverIps->forContext($ctx);
            if (empty($serverIp)) {
                return StageResult::failure($this->name(), 'IP عمومی سرور مقصد مشخص نیست، پس رکورد DNS ساخته نشد. در تنظیمات سرور، آدرس (host) را IP عمومی آن قرار دهید.', $this->elapsed());
            }

            $provider = new ArvanDnsProvider($setting);

            // Check if record already exists (idempotent — allow resume after partial failure).
            $existingId = $provider->findARecord($subdomain);
            if ($existingId) {
                Log::info('CIE/DNS: A record already exists, updating.', [
                    'subdomain' => $subdomain,
                    'record_id' => $existingId,
                    'new_ip'    => $serverIp,
                ]);
                $result = $provider->updateARecord($existingId, $subdomain, $serverIp, $setting->default_ttl);
                $recordId = $existingId;
            } else {
                $result   = $provider->createARecord($subdomain, $serverIp, $setting->default_ttl);
                $recordId = $result['record_id'] ?? null;
            }

            if (!$result['success']) {
                return StageResult::failure($this->name(), 'DNS record creation failed: ' . $result['message'], $this->elapsed());
            }

            // Propagation check — configurable, skip if env flag is set.
            $propagated    = false;
            $propagationMsg = 'Propagation check skipped.';

            if (!config('customer_installation.dns.skip_propagation', false)) {
                $maxWait      = (int) config('customer_installation.dns.propagation_max_wait_sec', 300);
                $pollInterval = max(1, (int) config('customer_installation.dns.propagation_poll_sec', 15));

                [$propagated, $seen] = $this->waitUntilPointsHere($fqdn, $serverIp, $maxWait, $pollInterval);
                $propagationMsg = $propagated
                    ? "DNS propagated successfully to {$serverIp}."
                    : "DNS propagation timed out after {$maxWait}s — continuing (Nginx will serve once DNS resolves).";

                if (!$propagated && $ctx->request->sslEnabled) {
                    // ISSUE_SSL would only time out against wherever the name
                    // resolves now; fail here, before upload and database.
                    // A failed stage keeps its pre-snapshot (record_id null
                    // for a new record), so rollback could not find the
                    // record this run created: delete it here.
                    if (!$existingId && $recordId) {
                        $provider->deleteRecord($recordId);
                    }
                    $seenText = $seen === [] ? 'هیچ IPی' : implode('، ', $seen);

                    return StageResult::failure($this->name(),
                        "رکورد DNS زیردامنهٔ «{$fqdn}» ساخته شد، اما پس از {$maxWait} ثانیه در DNS عمومی به IP این سرور ({$serverIp}) نرسید (پاسخ فعلی: {$seenText}). بدون آن گواهی SSL صادر نمی‌شود؛ تنظیمات DNS آروان‌کلاد را بررسی کنید و دوباره تلاش کنید.",
                        $this->elapsed());
                }
            } else {
                $propagated = true; // skipped = treat as passed
            }

            $snapshot = [
                'record_id'   => $recordId,
                'subdomain'   => $subdomain,
                'fqdn'        => $fqdn,
                'root_domain' => $rootDomain,
                'server_ip'   => $serverIp,
                'provider'    => $setting->provider,
            ];

            return StageResult::success($this->name(), [
                'record_id'       => $recordId,
                'subdomain'       => $subdomain,
                'fqdn'            => $fqdn,
                'server_ip'       => $serverIp,
                'dns_propagated'  => $propagated,
                'propagation_msg' => $propagationMsg,
            ], $snapshot, $this->elapsed());
        });
    }

    public function buildPreSnapshot(InstallationContext $ctx): array
    {
        $setting = DnsProviderSetting::active();
        if (!$setting || !$setting->isReady()) {
            return [];
        }

        $fqdn       = $ctx->request->domain;
        $rootDomain = $setting->root_domain;

        if (!$this->isDomainUnderRoot($fqdn, $rootDomain)) {
            return [];
        }

        $subdomain = rtrim(substr($fqdn, 0, -strlen('.' . $rootDomain)), '.');

        // Look for any existing record ID upfront so rollback knows what to remove.
        try {
            $provider  = new ArvanDnsProvider($setting);
            $recordId  = $provider->findARecord($subdomain);
        } catch (\Throwable) {
            $recordId = null;
        }

        return [
            'record_id'   => $recordId,   // null = nothing to delete on rollback (record didn't exist before)
            'subdomain'   => $subdomain,
            'fqdn'        => $fqdn,
            'root_domain' => $rootDomain,
        ];
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        $recordId = $snapshot['record_id'] ?? null;

        if (empty($recordId)) {
            Log::info('CIE/DNS: Rollback — no record_id in snapshot, nothing to delete.', [
                'installation_id' => $ctx->installation->id,
            ]);
            return;
        }

        try {
            $setting = DnsProviderSetting::active();
            if (!$setting || !$setting->isReady()) {
                Log::warning('CIE/DNS: Rollback — DNS provider not configured, cannot delete record.', ['record_id' => $recordId]);
                return;
            }

            $provider = new ArvanDnsProvider($setting);
            $result   = $provider->deleteRecord($recordId);

            if ($result['success']) {
                Log::info('CIE/DNS: Rollback — DNS record deleted.', [
                    'record_id'       => $recordId,
                    'installation_id' => $ctx->installation->id,
                ]);
            } else {
                Log::error('CIE/DNS: Rollback — failed to delete DNS record.', [
                    'record_id' => $recordId,
                    'error'     => $result['message'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('CIE/DNS: Rollback exception.', ['error' => $e->getMessage(), 'record_id' => $recordId]);
        }
    }

    /** True if $fqdn is a direct subdomain of $rootDomain. */
    private function isDomainUnderRoot(string $fqdn, string $rootDomain): bool
    {
        $fqdn       = strtolower(trim($fqdn, '. '));
        $rootDomain = strtolower(trim($rootDomain, '. '));

        if ($fqdn === $rootDomain) {
            return false; // apex record — not a subdomain
        }

        return str_ends_with($fqdn, '.' . $rootDomain);
    }

    /**
     * Poll public DNS until $fqdn resolves to the server (or, CDN-proxied,
     * only to ArvanCloud edge addresses). The attempt count is fixed up front
     * so the wait is bounded whatever the sleeper does.
     *
     * @return array{0: bool, 1: list<string>} [points here, last answer]
     */
    private function waitUntilPointsHere(string $fqdn, string $serverIp, int $maxWait, int $pollInterval): array
    {
        $attempts = max(1, intdiv(max(0, $maxWait), $pollInterval) + 1);
        $seen = [];

        Log::info('CIE/DNS: Waiting for DNS propagation', ['fqdn' => $fqdn, 'expected_ip' => $serverIp, 'attempts' => $attempts]);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $lookup = $this->dns->lookupA($fqdn);
            $seen = $lookup->ips;

            if ($lookup->isResolved() && $this->preflight->pointsHere($lookup, $serverIp)) {
                Log::info('CIE/DNS: DNS propagated', ['fqdn' => $fqdn, 'attempt' => $attempt, 'ips' => $seen]);

                return [true, $seen];
            }

            if ($attempt < $attempts) {
                ($this->sleeper)($pollInterval);
            }
        }

        Log::warning('CIE/DNS: DNS propagation timed out', ['fqdn' => $fqdn, 'expected_ip' => $serverIp, 'last_answer' => $seen]);

        return [false, $seen];
    }
}
