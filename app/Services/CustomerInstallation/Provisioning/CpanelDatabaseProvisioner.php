<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * Phase 14-F2 — real cPanel database provisioning via UAPI.
 *
 * Naming (the "{cpanel_username}_" prefix requirement) is NOT this class's
 * concern — DatabaseNameResolver already hands CreateDatabaseStage a fully
 * formed, cPanel-safe name before this provisioner ever sees it. This class
 * only translates create/grant/drop calls into the corresponding UAPI Mysql
 * module calls.
 */
class CpanelDatabaseProvisioner implements DatabaseProvisionerInterface
{
    public function __construct(private readonly CpanelUapiClient $uapi) {}

    public function createDatabase(InstallationContext $ctx, string $dbName): void
    {
        $this->uapi->call($this->server($ctx), 'Mysql', 'create_database', [
            'name' => $dbName,
        ]);
    }

    public function createUser(InstallationContext $ctx, string $dbUser, string $dbPassword): void
    {
        $this->uapi->call($this->server($ctx), 'Mysql', 'create_user', [
            'name'     => $dbUser,
            'password' => $dbPassword,
        ]);
    }

    public function grantPrivileges(InstallationContext $ctx, string $dbName, string $dbUser): void
    {
        $this->uapi->call($this->server($ctx), 'Mysql', 'set_privileges_on_user', [
            'user'       => $dbUser,
            'database'   => $dbName,
            'privileges' => 'ALL PRIVILEGES',
        ]);
    }

    public function dropDatabase(InstallationContext $ctx, string $dbName): void
    {
        try {
            $this->uapi->call($this->server($ctx), 'Mysql', 'delete_database', ['name' => $dbName]);
        } catch (RuntimeException) {
            // Best-effort — matches VpsDatabaseProvisioner::dropDatabase(),
            // which never checks its command's result either.
        }
    }

    public function dropUser(InstallationContext $ctx, string $dbUser): void
    {
        try {
            $this->uapi->call($this->server($ctx), 'Mysql', 'delete_user', ['name' => $dbUser]);
        } catch (RuntimeException) {
            // Best-effort, see dropDatabase().
        }
    }

    /**
     * Phase 14-G3 — existence probe via UAPI Mysql::list_databases. Returns
     * null (unverifiable) on any UAPI or transport failure — a probe must
     * never abort the deletion pipeline (see interface docblock).
     */
    public function databaseExists(InstallationContext $ctx, string $dbName): ?bool
    {
        try {
            $data = $this->uapi->call($this->server($ctx), 'Mysql', 'list_databases');
        } catch (RuntimeException | ConnectionException) {
            return null;
        }

        return $this->listContains($data, $dbName, 'database');
    }

    /** Phase 14-G3 — see databaseExists(). Probe via UAPI Mysql::list_users. */
    public function databaseUserExists(InstallationContext $ctx, string $dbUser): ?bool
    {
        try {
            $data = $this->uapi->call($this->server($ctx), 'Mysql', 'list_users');
        } catch (RuntimeException | ConnectionException) {
            return null;
        }

        return $this->listContains($data, $dbUser, 'user');
    }

    /**
     * UAPI list responses vary between plain string arrays and arrays of
     * objects keyed by entity type — handle both defensively.
     */
    private function listContains(array $data, string $needle, string $objectKey): bool
    {
        foreach ($data as $entry) {
            $name = is_array($entry) ? ($entry[$objectKey] ?? $entry['name'] ?? null) : $entry;
            if ($name === $needle) {
                return true;
            }
        }

        return false;
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
