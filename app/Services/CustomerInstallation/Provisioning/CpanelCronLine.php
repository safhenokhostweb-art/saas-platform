<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * Phase 14-F3 — shared "install/remove one crontab line via cPanel UAPI"
 * mechanism. Used by both CpanelCronProvisioner (Laravel scheduler) and
 * CpanelQueueProvisioner (cron-based queue worker, since cPanel has no
 * Supervisor) — identical UAPI call shape, only the command string differs.
 * Extracted here specifically so neither provisioner duplicates this logic.
 */
final class CpanelCronLine
{
    public function __construct(private readonly CpanelUapiClient $uapi) {}

    public function install(InstallationContext $ctx, string $command, string $failureContext): ProvisioningResult
    {
        try {
            $this->uapi->call($this->server($ctx), 'Cron', 'add_line', [
                'command' => $command,
                'minute'  => '*',
                'hour'    => '*',
                'day'     => '*',
                'month'   => '*',
                'weekday' => '*',
            ]);
        } catch (RuntimeException | ConnectionException $e) {
            // Phase 14-G1 — a UAPI-level error (bad response, cPanel-reported
            // failure) throws RuntimeException; a transport-level failure
            // (timeout, DNS, TLS) throws Illuminate\Http\Client\ConnectionException,
            // which does NOT extend RuntimeException. Both must degrade to a
            // skip here — this provisioner is only ever used for optional
            // infra (isCapabilityRequiredToProceed() === false on the calling
            // stage), so a slow/unreachable cPanel box must not abort the
            // whole installation.
            return ProvisioningResult::skipped("{$failureContext}: " . $e->getMessage());
        }

        $line = "* * * * * {$command}";

        return ProvisioningResult::installed(['cron_line' => $line], ['cron_line' => $line]);
    }

    /** Best-effort — must not throw. */
    public function remove(InstallationContext $ctx, array $snapshot): void
    {
        if (empty($snapshot['cron_line'])) {
            return;
        }

        try {
            $this->uapi->call($this->server($ctx), 'Cron', 'remove_line', ['line' => $snapshot['cron_line']]);
        } catch (RuntimeException | ConnectionException) {
            // best-effort, matches VpsCronProvisioner::removeScheduler() /
            // VpsDatabaseProvisioner's rollback methods (Phase 14-F2), which
            // also never check their command's result.
        }
    }

    /**
     * @throws RuntimeException if the installation's remote server record is gone.
     */
    private function server(InstallationContext $ctx): CustomerRemoteServer
    {
        $server = CustomerRemoteServer::find($ctx->installation->remote_server_id);

        if (!$server) {
            throw new RuntimeException('CustomerRemoteServer record not found for this installation.');
        }

        return $server;
    }
}
