<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Enums\DriverType;
use Illuminate\Support\Str;

/**
 * Phase 14-F2 — single place that knows how database/user identifiers are
 * named per hosting type, so neither CreateDatabaseStage nor any
 * DatabaseProvisionerInterface implementation duplicates naming logic.
 *
 * VPS: unchanged from the pre-14-F2 CreateDatabaseStage — random, no prefix
 * requirement (root MySQL access, no per-account namespace).
 *
 * cPanel: MySQL's own "user" column is CHAR(32), and cPanel additionally
 * requires every database/user name it creates to start with
 * "{cpanel_username}_". Both constraints are enforced here by sizing the
 * random suffix to whatever room is left after the prefix, rather than
 * risking a UAPI rejection for a too-long identifier.
 */
final class DatabaseNameResolver
{
    private const MYSQL_USER_MAX_LENGTH = 32;
    private const MIN_SUFFIX_LENGTH = 4;
    private const MAX_SUFFIX_LENGTH = 10;

    public function resolve(InstallationContext $ctx, ?CustomerRemoteServer $server): DatabaseNames
    {
        return match ($ctx->connection->driverType()) {
            DriverType::CPANEL, DriverType::DIRECTADMIN => $this->prefixedNames($server),
            default => $this->vpsNames($ctx),
        };
    }

    /** Unchanged from the original CreateDatabaseStage naming scheme. */
    private function vpsNames(InstallationContext $ctx): DatabaseNames
    {
        return new DatabaseNames(
            database: 'cust_' . Str::random(6) . '_' . $ctx->installation->id,
            username: 'u_' . Str::random(8),
        );
    }

    private function prefixedNames(?CustomerRemoteServer $server): DatabaseNames
    {
        $prefix = $this->sanitizePrefix($server->username ?? null);

        $maxSuffixLength = self::MYSQL_USER_MAX_LENGTH - strlen($prefix) - 1; // -1 for the underscore
        $suffixLength = max(self::MIN_SUFFIX_LENGTH, min(self::MAX_SUFFIX_LENGTH, $maxSuffixLength));

        return new DatabaseNames(
            database: $prefix . '_' . strtolower(Str::random($suffixLength)),
            username: $prefix . '_' . strtolower(Str::random($suffixLength)),
        );
    }

    /**
     * cPanel account usernames are already alphanumeric by cPanel's own
     * rules, but this is defensive against a malformed record — strips
     * anything that isn't a-z0-9 and caps length so the prefix alone can
     * never consume the entire MySQL identifier budget.
     */
    private function sanitizePrefix(?string $username): string
    {
        $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $username));

        if ($clean === '') {
            $clean = 'cpuser';
        }

        // Leave room for "_" + MIN_SUFFIX_LENGTH after the prefix.
        return substr($clean, 0, self::MYSQL_USER_MAX_LENGTH - self::MIN_SUFFIX_LENGTH - 1);
    }
}
