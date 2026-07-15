<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Provisioning\DatabaseNameResolver;
use App\Services\CustomerInstallation\Provisioning\DatabaseProvisionerFactory;

/**
 * Phase 14-F2 — this stage no longer knows `mysql`, SSH, or cPanel. It
 * resolves hosting-appropriate names via DatabaseNameResolver, resolves the
 * right DatabaseProvisionerInterface via DatabaseProvisionerFactory for
 * whatever driver is connected, and calls only that interface. The .env
 * update below is NOT hosting-specific: it uses $ctx->connection->execute(),
 * the same generic primitive every driver (including CpanelDeploymentDriver)
 * implements identically over its own transport.
 */
class CreateDatabaseStage extends AbstractStage
{
    public function __construct(private readonly DatabaseProvisionerFactory $provisioners) {}

    public function name(): string { return 'CREATE_DATABASE'; }
    public function label(): string { return 'ساخت پایگاه داده'; }
    public function estimatedSeconds(): int { return 3; }
    public function canRollback(): bool { return true; }

    /**
     * Phase 14-E — this stage's mechanism requires the connected driver to
     * be able to create a database. Essential: a site cannot run without
     * one, so a missing capability fails the installation rather than
     * skipping (isCapabilityRequiredToProceed() default = true).
     */
    public function requiredCapabilities(): array
    {
        return ['supportsDatabaseCreation'];
    }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $server = CustomerRemoteServer::find($ctx->installation->remote_server_id);
            $names  = (new DatabaseNameResolver())->resolve($ctx, $server);
            $dbPass = $this->generateDbPassword();

            $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());

            $provisioner->createDatabase($ctx, $names->database);
            $provisioner->createUser($ctx, $names->username, $dbPass);
            $provisioner->grantPrivileges($ctx, $names->database, $names->username);

            // Update .env with real DB credentials.
            // Use | as sed delimiter (password charset never includes |).
            $webRoot = $ctx->webRoot();
            $envPath = $webRoot . '/.env';
            $sedCommands = [
                "sed -i 's|__DB_NAME__|{$names->database}|' " . escapeshellarg($envPath),
                "sed -i 's|__DB_USER__|{$names->username}|' " . escapeshellarg($envPath),
                "sed -i 's|__DB_PASS__|{$dbPass}|' " . escapeshellarg($envPath),
            ];
            foreach ($sedCommands as $cmd) {
                $ctx->connection->execute($cmd);
            }

            return StageResult::success($this->name(), [
                'db_name' => $names->database,
                'db_user' => $names->username,
            ], [
                'db_name' => $names->database,
                'db_user' => $names->username,
            ], $this->elapsed());
        });
    }

    /**
     * Generate a 32-char password that satisfies MySQL validate_password STRONG policy.
     * Charset excludes /, &, \, ', ", `, $, | to be safe in sed and shell substitutions.
     */
    private function generateDbPassword(): string
    {
        $lower   = 'abcdefghijklmnopqrstuvwxyz';
        $upper   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits  = '0123456789';
        $special = '!@#%^*_+=:,.?~';

        $pool    = $lower . $upper . $digits . $special;
        $poolLen = strlen($pool);

        // Guarantee at least one character from each required class
        $pass  = $lower[random_int(0, 25)];
        $pass .= $upper[random_int(0, 25)];
        $pass .= $digits[random_int(0, 9)];
        $pass .= $special[random_int(0, strlen($special) - 1)];

        for ($i = 4; $i < 32; $i++) {
            $pass .= $pool[random_int(0, $poolLen - 1)];
        }

        return str_shuffle($pass);
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());

        if (!empty($snapshot['db_name'])) {
            $provisioner->dropDatabase($ctx, $snapshot['db_name']);
        }
        if (!empty($snapshot['db_user'])) {
            $provisioner->dropUser($ctx, $snapshot['db_user']);
        }
    }
}
