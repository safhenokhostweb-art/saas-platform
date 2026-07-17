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
        $server = $this->server($ctx);
        $line   = "* * * * * {$command}";

        // Phase 15-G — Part 2: check for the exact command before adding a
        // new line. Without this, resuming CONFIGURE_SCHEDULER/CONFIGURE_QUEUE
        // after a crash that happened between the UAPI call succeeding and
        // CustomerInstallationOrchestrator recording the stage complete would
        // add a second, duplicate line (see CpanelCronDuplicationOnResumeTest,
        // Phase 15-F). If the existence check itself fails (listing not
        // available/blocked), fall through to add_line as before — never
        // worse than the pre-Part-2 behavior, only potentially not better.
        if ($this->lineAlreadyExists($server, $command)) {
            return ProvisioningResult::installed(
                ['cron_line' => $line, 'already_existed' => true],
                ['cron_line' => $line]
            );
        }

        try {
            $this->uapi->call($server, 'Cron', 'add_line', [
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

        return ProvisioningResult::installed(['cron_line' => $line], ['cron_line' => $line]);
    }

    /**
     * Best-effort existence check — UAPI function name (Cron::list_lines) is
     * unverified against a live cPanel account, same confidence posture as
     * every other UAPI call added in Phase 15-D/15-E (see this class's
     * docblock precedent). Any failure here (wrong function name, transport
     * error) is swallowed and treated as "unknown", never as "not found" —
     * install() falls back to its prior unconditional add_line behavior.
     */
    private function lineAlreadyExists(CustomerRemoteServer $server, string $command): bool
    {
        try {
            $data = $this->uapi->call($server, 'Cron', 'list_lines');
        } catch (RuntimeException | ConnectionException) {
            return false;
        }

        foreach ($data as $entry) {
            $existingCommand = is_array($entry) ? ($entry['command'] ?? null) : $entry;

            if ($existingCommand !== null && str_contains((string) $existingCommand, $command)) {
                return true;
            }
        }

        return false;
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
