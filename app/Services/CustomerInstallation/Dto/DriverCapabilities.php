<?php

namespace App\Services\CustomerInstallation\Dto;

/**
 * Declares what hosting features a deployment driver supports.
 * Every stage queries this before executing driver-specific operations.
 * Never hardcode hosting-type checks in pipeline stages — check capabilities instead.
 */
readonly class DriverCapabilities
{
    public function __construct(
        public readonly bool $supportsQueue            = false,
        public readonly bool $supportsScheduler        = false,
        public readonly bool $supportsCron             = false,
        public readonly bool $supportsSupervisor       = false,
        public readonly bool $supportsDatabaseCreation = false,
        public readonly bool $supportsSSL              = false,
        public readonly bool $supportsStorageLink      = false,
        public readonly bool $supportsRedis            = false,
        public readonly bool $supportsDocker           = false,
        public readonly bool $supportsReboot           = false,
    ) {}

    // ─── Named constructors per hosting type ──────────────────────────────────

    public static function forVps(): self
    {
        return new self(
            supportsQueue:            true,
            supportsScheduler:        true,
            supportsCron:             true,
            supportsSupervisor:       true,
            supportsDatabaseCreation: true,
            supportsSSL:              true,
            supportsStorageLink:      true,
            supportsRedis:            true,
            supportsDocker:           true,
            supportsReboot:           true,
        );
    }

    public static function forCpanel(): self
    {
        return new self(
            supportsQueue:            false,
            supportsScheduler:        true,  // via cPanel built-in cron
            supportsCron:             true,  // via cPanel Cron Jobs UI
            supportsSupervisor:       false, // not available on shared hosting
            supportsDatabaseCreation: true,  // via cPanel MySQL Databases API
            supportsSSL:              true,  // via cPanel SSL/TLS or Let's Encrypt
            supportsStorageLink:      false, // no shell access to run artisan
            supportsRedis:            false, // not available on most shared plans
            supportsDocker:           false,
            supportsReboot:           false,
        );
    }

    public static function forDirectAdmin(): self
    {
        return new self(
            supportsQueue:            false,
            supportsScheduler:        true,  // via DirectAdmin cron
            supportsCron:             true,
            supportsSupervisor:       false,
            supportsDatabaseCreation: true,  // via DirectAdmin API
            supportsSSL:              true,
            supportsStorageLink:      false,
            supportsRedis:            false,
            supportsDocker:           false,
            supportsReboot:           false,
        );
    }

    public static function forSftp(): self
    {
        // Pure SFTP: upload only, no command execution, no service configuration
        return new self(
            supportsQueue:            false,
            supportsScheduler:        false,
            supportsCron:             false,
            supportsSupervisor:       false,
            supportsDatabaseCreation: false,
            supportsSSL:              false,
            supportsStorageLink:      false,
            supportsRedis:            false,
            supportsDocker:           false,
            supportsReboot:           false,
        );
    }

    public static function forDocker(): self
    {
        return new self(
            supportsQueue:            true,
            supportsScheduler:        true,
            supportsCron:             true,
            supportsSupervisor:       false, // containers use process managers, not supervisord
            supportsDatabaseCreation: true,
            supportsSSL:              false, // handled by reverse proxy, not container
            supportsStorageLink:      true,
            supportsRedis:            true,
            supportsDocker:           true,
            supportsReboot:           true,  // restart container
        );
    }

    // ─── Serialization ────────────────────────────────────────────────────────

    public function toArray(): array
    {
        return [
            'supports_queue'             => $this->supportsQueue,
            'supports_scheduler'         => $this->supportsScheduler,
            'supports_cron'              => $this->supportsCron,
            'supports_supervisor'        => $this->supportsSupervisor,
            'supports_database_creation' => $this->supportsDatabaseCreation,
            'supports_ssl'               => $this->supportsSSL,
            'supports_storage_link'      => $this->supportsStorageLink,
            'supports_redis'             => $this->supportsRedis,
            'supports_docker'            => $this->supportsDocker,
            'supports_reboot'            => $this->supportsReboot,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            supportsQueue:            (bool) ($data['supports_queue']             ?? false),
            supportsScheduler:        (bool) ($data['supports_scheduler']         ?? false),
            supportsCron:             (bool) ($data['supports_cron']              ?? false),
            supportsSupervisor:       (bool) ($data['supports_supervisor']        ?? false),
            supportsDatabaseCreation: (bool) ($data['supports_database_creation'] ?? false),
            supportsSSL:              (bool) ($data['supports_ssl']               ?? false),
            supportsStorageLink:      (bool) ($data['supports_storage_link']      ?? false),
            supportsRedis:            (bool) ($data['supports_redis']             ?? false),
            supportsDocker:           (bool) ($data['supports_docker']            ?? false),
            supportsReboot:           (bool) ($data['supports_reboot']            ?? false),
        );
    }
}
