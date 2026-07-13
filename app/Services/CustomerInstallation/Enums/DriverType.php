<?php

namespace App\Services\CustomerInstallation\Enums;

enum DriverType: string
{
    case SSH            = 'ssh';
    case CPANEL         = 'cpanel';
    case DIRECTADMIN    = 'directadmin';
    case SFTP           = 'sftp';
    case DOCKER         = 'docker';
    case FAKE           = 'fake';

    public function label(): string
    {
        return match($this) {
            self::SSH         => 'SSH / VPS',
            self::CPANEL      => 'cPanel Shared Hosting',
            self::DIRECTADMIN => 'DirectAdmin Shared Hosting',
            self::SFTP        => 'Pure SFTP',
            self::DOCKER      => 'Docker Container',
            self::FAKE        => 'Fake (Testing)',
        };
    }

    public function isImplemented(): bool
    {
        return match($this) {
            // Phase 14-C — cPanel connects over SSH transport (password/key
            // auth only; api_token is rejected by CpanelDeploymentDriver
            // itself). directadmin/sftp/docker remain architecture stubs.
            self::SSH, self::CPANEL, self::FAKE => true,
            default                              => false,
        };
    }

    /** Default TCP port for this driver type. */
    public function defaultPort(): int
    {
        return match($this) {
            self::SSH, self::DOCKER => 22,
            self::SFTP              => 22,
            self::CPANEL            => 2083,
            self::DIRECTADMIN       => 2222,
            self::FAKE              => 22,
        };
    }
}
