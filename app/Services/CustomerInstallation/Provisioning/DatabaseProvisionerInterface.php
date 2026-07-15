<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use RuntimeException;

/**
 * Phase 14-F2 — hosting-agnostic database provisioning contract.
 *
 * CreateDatabaseStage calls only this interface; it never knows whether the
 * underlying mechanism is a root `mysql` CLI command (VPS) or a cPanel UAPI
 * HTTP call. Resolved per-installation via DatabaseProvisionerFactory,
 * exactly mirroring how DriverFactory resolves connection drivers.
 *
 * All methods throw RuntimeException with a clear diagnostic message on
 * failure — AbstractStage::run() already converts any thrown Throwable into
 * a StageResult::failure(), so implementations do not need to return a
 * result object.
 */
interface DatabaseProvisionerInterface
{
    /** @throws RuntimeException */
    public function createDatabase(InstallationContext $ctx, string $dbName): void;

    /** @throws RuntimeException */
    public function createUser(InstallationContext $ctx, string $dbUser, string $dbPassword): void;

    /** @throws RuntimeException */
    public function grantPrivileges(InstallationContext $ctx, string $dbName, string $dbUser): void;

    /**
     * Rollback operation — best-effort by design (matches the pre-existing
     * VPS rollback() behavior, which never checked command failure either).
     * Must not throw.
     */
    public function dropDatabase(InstallationContext $ctx, string $dbName): void;

    /**
     * Rollback operation — best-effort, must not throw. See dropDatabase().
     */
    public function dropUser(InstallationContext $ctx, string $dbUser): void;

    /**
     * Phase 14-G3 — read-only existence probe, used by the customer DELETION
     * pipeline (RemoveDatabaseStage / PreDeleteAuditStage / VerifyDeleteStage)
     * so no stage ever runs hosting-specific SQL/API calls itself.
     *
     * Returns true/false when the driver can answer authoritatively, or null
     * when the question is unverifiable on this transport (e.g. a UAPI
     * timeout) — callers must treat null as "unknown, do not fail on it".
     * Must not throw.
     */
    public function databaseExists(InstallationContext $ctx, string $dbName): ?bool;

    /**
     * Phase 14-G3 — read-only existence probe for the DB user. Same
     * null-means-unverifiable contract as databaseExists(). Must not throw.
     */
    public function databaseUserExists(InstallationContext $ctx, string $dbUser): ?bool;
}
