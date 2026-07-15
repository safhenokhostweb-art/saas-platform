<?php

namespace App\Services\CustomerInstallation\Dto;

/**
 * Declares what hosting features a deployment driver supports.
 * Every stage queries this before executing driver-specific operations.
 * Never hardcode hosting-type checks in pipeline stages — check capabilities instead.
 *
 * Phase 14-E — two corrections made while wiring this DTO into the actual
 * pipeline (previously nothing consulted it, so these were untested claims):
 *
 * 1. Added supportsWebServerConfiguration, distinct from supportsSSL. The
 *    pipeline's ConfigureWebServerStage writes raw nginx vhost files and
 *    reloads the service — an operation entirely separate from "can this
 *    driver issue an SSL certificate".
 * 2. supportsDatabaseCreation for cPanel/DirectAdmin corrected from true to
 *    false. It was declared true because the cPanel/DirectAdmin *API* can
 *    create a database — but CreateDatabaseStage ran `mysql -u root ...`
 *    over the transport's execute(), which no shared-hosting account can
 *    ever satisfy (no root MySQL CLI access). This flag must describe what
 *    the CURRENT stage implementation can actually do, not what a future
 *    API integration could do.
 *
 * Phase 14-F2 — supportsDatabaseCreation for cPanel flipped back to true:
 * CreateDatabaseStage no longer runs `mysql -u root` directly for any
 * driver — it resolves a DatabaseProvisionerInterface via
 * DatabaseProvisionerFactory, and CpanelDatabaseProvisioner genuinely
 * creates the database/user/privileges through the cPanel UAPI (Mysql
 * module), verified by CpanelDatabaseProvisionerTest with Http::fake().
 * DirectAdmin stays false — no DirectAdmin provisioner exists yet; flip it
 * only once one does.
 *
 * Phase 14-F3 — added supportsQueueWorker, distinct from supportsSupervisor.
 * supportsSupervisor means "this driver can run a Supervisor-managed
 * persistent daemon" and correctly stays false for cPanel (shared hosting
 * genuinely cannot run persistent daemons — this is not a pipeline
 * limitation, it does not change). supportsQueueWorker means "this driver
 * has SOME way to run queued jobs at all" — true for VPS (via Supervisor)
 * AND cPanel (via a cron-based `queue:work --stop-when-empty` job, the
 * standard shared-hosting pattern — see CpanelQueueProvisioner). A single
 * derived flag was used instead of gating on "supportsSupervisor OR
 * supportsCronBasedQueue" because StageCapabilityGate's requiredCapabilities()
 * is an AND-list, not an OR-list — see the Phase 14-F3 report for the
 * alternatives considered. supportsCron itself was NOT renamed to
 * "supportsCronManagement" (an example name floated for this phase): it was
 * already true for both VPS and cPanel and already correctly consumed by
 * ConfigureSchedulerStage, so renaming it would only add churn for no
 * behavior change.
 *
 * Phase 14-G3 — added supportsDatabaseDeletion, consumed by the Customer
 * DELETION pipeline (RemoveDatabaseStage / RemoveDatabaseUserStage /
 * PreDeleteAuditStage / VerifyDeleteStage). Kept separate from
 * supportsDatabaseCreation on principle (a flag must describe what the
 * current implementation can actually do per operation), though today the
 * two have identical values everywhere: VPS deletes via root mysql CLI,
 * cPanel via UAPI Mysql::delete_database/delete_user (the same calls the
 * install rollback path has used since 14-F2), and DirectAdmin/SFTP have no
 * provisioner at all.
 *
 * Phase 14-F5 — supportsStorageLink for cPanel corrected from false to true.
 * It was declared false with the reasoning "no shell access to run
 * artisan" — a claim written before CpanelDeploymentDriver (Phase 14-C)
 * existed. Since 14-C, cPanel connects over the same SSH transport as VPS,
 * so `php artisan storage:link` (a symlink inside the account's own home
 * directory, no root needed) already works there. ConfigureStorageStage
 * itself was never gated on this flag before 14-F5 (it always ran
 * unconditionally), so this correction has no observable behavior change —
 * it only makes the declared metadata match what was already true.
 */
readonly class DriverCapabilities
{
    public function __construct(
        public readonly bool $supportsQueue                   = false,
        public readonly bool $supportsScheduler               = false,
        public readonly bool $supportsCron                    = false,
        public readonly bool $supportsSupervisor              = false,
        public readonly bool $supportsDatabaseCreation        = false,
        public readonly bool $supportsSSL                     = false,
        public readonly bool $supportsStorageLink             = false,
        public readonly bool $supportsRedis                   = false,
        public readonly bool $supportsDocker                  = false,
        public readonly bool $supportsReboot                  = false,
        public readonly bool $supportsWebServerConfiguration  = false,
        public readonly bool $supportsQueueWorker              = false,
        public readonly bool $supportsDatabaseDeletion         = false,
    ) {}

    // ─── Named constructors per hosting type ──────────────────────────────────

    public static function forVps(): self
    {
        return new self(
            supportsQueue:                  true,
            supportsScheduler:              true,
            supportsCron:                   true,
            supportsSupervisor:             true,
            supportsDatabaseCreation:       true,
            supportsSSL:                    true,
            supportsStorageLink:            true,
            supportsRedis:                  true,
            supportsDocker:                 true,
            supportsReboot:                 true,
            supportsWebServerConfiguration: true,
            supportsQueueWorker:            true,
            supportsDatabaseDeletion:       true,
        );
    }

    public static function forCpanel(): self
    {
        return new self(
            supportsQueue:                  false,
            supportsScheduler:              true,  // via cPanel built-in cron
            supportsCron:                   true,  // via cPanel Cron Jobs UI
            supportsSupervisor:             false, // not available on shared hosting
            supportsDatabaseCreation:       true,  // Phase 14-F2: CpanelDatabaseProvisioner
                                                    // via cPanel UAPI Mysql module — see class docblock.
            supportsSSL:                   true,  // via cPanel SSL/TLS or Let's Encrypt
            supportsStorageLink:            true,  // Phase 14-F5: cPanel connects over SSH
                                                    // (14-C) — `artisan storage:link` works
                                                    // the same as VPS, no root needed.
            supportsRedis:                  false, // not available on most shared plans
            supportsDocker:                 false,
            supportsReboot:                 false,
            supportsWebServerConfiguration: false, // WHM/cPanel manages vhosts automatically;
                                                    // no /etc/nginx write access, no systemctl.
            supportsQueueWorker:            true,  // Phase 14-F3: CpanelQueueProvisioner —
                                                    // cron-based `queue:work --stop-when-empty`,
                                                    // not Supervisor. supportsSupervisor stays false.
            supportsDatabaseDeletion:       true,  // Phase 14-G3: UAPI Mysql::delete_database /
                                                    // delete_user via CpanelDatabaseProvisioner.
        );
    }

    public static function forDirectAdmin(): self
    {
        return new self(
            supportsQueue:                  false,
            supportsScheduler:              true,  // via DirectAdmin cron
            supportsCron:                   true,
            supportsSupervisor:             false,
            supportsDatabaseCreation:       false, // Phase 14-E: same reasoning as forCpanel() —
                                                    // no root mysql CLI on a shared-hosting account.
            supportsSSL:                    true,
            supportsStorageLink:            false,
            supportsRedis:                  false,
            supportsDocker:                 false,
            supportsReboot:                 false,
            supportsWebServerConfiguration: false, // DirectAdmin manages vhosts automatically.
            supportsQueueWorker:            false, // no DirectAdmin queue provisioner exists yet.
            supportsDatabaseDeletion:       false, // no DirectAdmin provisioner exists yet.
        );
    }

    public static function forSftp(): self
    {
        // Pure SFTP: upload only, no command execution, no service configuration
        return new self(
            supportsQueue:                  false,
            supportsScheduler:              false,
            supportsCron:                   false,
            supportsSupervisor:             false,
            supportsDatabaseCreation:       false,
            supportsSSL:                    false,
            supportsStorageLink:            false,
            supportsRedis:                  false,
            supportsDocker:                 false,
            supportsReboot:                 false,
            supportsWebServerConfiguration: false,
            supportsQueueWorker:            false,
            supportsDatabaseDeletion:       false,
        );
    }

    public static function forDocker(): self
    {
        return new self(
            supportsQueue:                  true,
            supportsScheduler:              true,
            supportsCron:                   true,
            supportsSupervisor:             false, // containers use process managers, not supervisord
            supportsDatabaseCreation:       true,
            supportsSSL:                    false, // handled by reverse proxy, not container
            supportsStorageLink:            true,
            supportsRedis:                  true,
            supportsDocker:                 true,
            supportsReboot:                 true,  // restart container
            supportsWebServerConfiguration: false, // reverse proxy owns vhost/SSL config, not this pipeline
            supportsQueueWorker:            true,  // QueueProvisionerFactory maps Docker to
                                                    // VpsQueueProvisioner (Supervisor-style).
            supportsDatabaseDeletion:       true,  // DatabaseProvisionerFactory maps Docker to
                                                    // VpsDatabaseProvisioner (root mysql CLI).
        );
    }

    // ─── Serialization ────────────────────────────────────────────────────────

    public function toArray(): array
    {
        return [
            'supports_queue'                     => $this->supportsQueue,
            'supports_scheduler'                 => $this->supportsScheduler,
            'supports_cron'                       => $this->supportsCron,
            'supports_supervisor'                => $this->supportsSupervisor,
            'supports_database_creation'         => $this->supportsDatabaseCreation,
            'supports_ssl'                        => $this->supportsSSL,
            'supports_storage_link'              => $this->supportsStorageLink,
            'supports_redis'                      => $this->supportsRedis,
            'supports_docker'                     => $this->supportsDocker,
            'supports_reboot'                     => $this->supportsReboot,
            'supports_web_server_configuration'  => $this->supportsWebServerConfiguration,
            'supports_queue_worker'              => $this->supportsQueueWorker,
            'supports_database_deletion'         => $this->supportsDatabaseDeletion,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            supportsQueue:                  (bool) ($data['supports_queue']                    ?? false),
            supportsScheduler:              (bool) ($data['supports_scheduler']                ?? false),
            supportsCron:                   (bool) ($data['supports_cron']                     ?? false),
            supportsSupervisor:             (bool) ($data['supports_supervisor']               ?? false),
            supportsDatabaseCreation:       (bool) ($data['supports_database_creation']         ?? false),
            supportsSSL:                    (bool) ($data['supports_ssl']                      ?? false),
            supportsStorageLink:            (bool) ($data['supports_storage_link']             ?? false),
            supportsRedis:                  (bool) ($data['supports_redis']                    ?? false),
            supportsDocker:                 (bool) ($data['supports_docker']                    ?? false),
            supportsReboot:                 (bool) ($data['supports_reboot']                    ?? false),
            supportsWebServerConfiguration: (bool) ($data['supports_web_server_configuration']  ?? false),
            supportsQueueWorker:            (bool) ($data['supports_queue_worker']              ?? false),
            supportsDatabaseDeletion:       (bool) ($data['supports_database_deletion']          ?? false),
        );
    }
}
