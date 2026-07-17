<?php

namespace App\Services\CustomerInstallation\Drivers;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Dto\CommandResult;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Dto\ValidationResult;
use App\Services\CustomerInstallation\Enums\DriverType;
use RuntimeException;

/**
 * cPanel shared hosting driver.
 *
 * Phase 14-C — Connection layer decision: SSH, not pure cPanel UAPI/WHM API.
 *
 * RemoteConnectionInterface's primitives (execute/upload/download/fileExists/
 * diskFree) are generic shell/file operations. cPanel's UAPI/WHM API expose
 * specific verbs (create database, create database user, manage cron, issue
 * SSL) — not "run this arbitrary command" or "write this arbitrary file",
 * which is what every existing pipeline Stage actually calls. There is no
 * way to satisfy this interface's contract through the API alone without
 * redesigning the Stage layer to call cPanel-specific high-level methods —
 * out of scope for this phase (Stages must not change). SSH is therefore the
 * only transport that can make connect()/execute()/upload()/etc. real today,
 * and this driver delegates to the same phpseclib3-based transport already
 * validated in SshRemoteDriver rather than re-implementing it.
 *
 * Consequence: only password (or key) authentication actually connects.
 * auth_method=api_token is accepted at the model/validation layer (existing
 * CustomerRemoteServerAdminController form) but rejected by validate() /
 * connect() here with a clear error — see guardSshTransportSupported().
 * Implementing real UAPI-based execution is a larger, separate phase.
 *
 * Capabilities: declared via DriverCapabilities::forCpanel() — cron,
 * scheduler, SSL, and database-creation are marked supported because the
 * cPanel API surface can do them in principle; supervisor/redis/queue/
 * storage-link/docker/reboot stay false because shared hosting genuinely
 * cannot do them. IMPORTANT: capabilities() is descriptive metadata only —
 * no pipeline Stage currently consults it (confirmed unchanged in this
 * phase). CreateDatabaseStage still runs a raw `mysql -u root` shell command
 * and ConfigureWebServerStage still writes /etc/nginx + `systemctl reload`;
 * both require true root/VPS access that a cPanel hosting account does not
 * have, and will fail against a real cPanel box regardless of this driver's
 * quality. Adapting those Stages is explicitly out of scope for Phase 14-C —
 * see the Phase 14-C report, "Pipeline Compatibility" section.
 */
class CpanelDeploymentDriver implements DeploymentDriverInterface
{
    private readonly SshRemoteDriver $transport;

    public function __construct(
        private readonly CustomerRemoteServer $server,
        private readonly CredentialVault $vault,
    ) {
        $this->transport = new SshRemoteDriver($server, $vault);
    }

    // ─── DeploymentDriverInterface ────────────────────────────────────────────

    public function driverType(): DriverType
    {
        return DriverType::CPANEL;
    }

    public function capabilities(): DriverCapabilities
    {
        return DriverCapabilities::forCpanel();
    }

    public function validate(): ValidationResult
    {
        $errors = [];

        if (empty($this->server->host)) {
            $errors[] = 'Host is required';
        }

        if (empty($this->server->username)) {
            $errors[] = 'cPanel username is required';
        }

        if ($this->isApiTokenAuth()) {
            $errors[] = 'cPanel API-token authentication is registered but not yet supported for '
                . 'pipeline operations — this driver connects over SSH only in Phase 14-C. '
                . 'Recreate this deployment target with password (or key) authentication using an '
                . 'SSH-enabled cPanel account credential, or wait for a future UAPI-based phase.';
        } elseif ($this->server->isKeyAuth()) {
            if (empty($this->server->private_key_encrypted)) {
                $errors[] = 'SSH private key is required for key authentication';
            }
        } else {
            if (empty($this->server->password_encrypted)) {
                $errors[] = 'Password is required for password authentication';
            }
        }

        return empty($errors) ? ValidationResult::pass() : ValidationResult::fail($errors);
    }

    public function rollbackStage(string $stageName, array $snapshot): void
    {
        // Stage-specific rollback logic resides in each Stage class.
        // cPanel driver has no driver-level cleanup beyond the session lifecycle.
    }

    // ─── RemoteConnectionInterface — delegates to SSH transport ──────────────

    public function connect(): void
    {
        $this->guardSshTransportSupported();
        $this->transport->connect();
    }

    public function testConnection(): ConnectionTestResult
    {
        try {
            $this->guardSshTransportSupported();
        } catch (RuntimeException $e) {
            return ConnectionTestResult::failure($e->getMessage());
        }

        return $this->transport->testConnection();
    }

    public function execute(string $command, array $env = []): CommandResult
    {
        return $this->transport->execute($command, $env);
    }

    public function upload(string $localPath, string $remotePath, ?callable $progress = null): void
    {
        $this->transport->upload($localPath, $remotePath, $progress);
    }

    public function download(string $remotePath, string $localPath): void
    {
        $this->transport->download($remotePath, $localPath);
    }

    public function fileExists(string $remotePath): bool
    {
        return $this->transport->fileExists($remotePath);
    }

    public function diskFree(string $path): int
    {
        return $this->transport->diskFree($path);
    }

    public function disconnect(): void
    {
        $this->transport->disconnect();
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    /**
     * @throws RuntimeException if this connection is configured for the
     * not-yet-implemented API-token transport.
     */
    private function guardSshTransportSupported(): void
    {
        if ($this->isApiTokenAuth()) {
            throw new RuntimeException(
                'cPanel API-token authentication is not yet supported for live connections '
                . '(Phase 14-C implements SSH transport only). Use password or key authentication.'
            );
        }
    }

    /**
     * True for CustomerRemoteServer's plain 'api_token' auth_method.
     */
    private function isApiTokenAuth(): bool
    {
        return str_contains((string) ($this->server->auth_method ?? ''), 'api_token');
    }
}
