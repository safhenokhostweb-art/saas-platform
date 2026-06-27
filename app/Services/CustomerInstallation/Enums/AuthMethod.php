<?php

namespace App\Services\CustomerInstallation\Enums;

enum AuthMethod: string
{
    case SSH_KEY                = 'ssh_key';
    case SSH_PASSWORD           = 'ssh_password';
    case CPANEL_API_TOKEN       = 'cpanel_api_token';
    case CPANEL_PASSWORD        = 'cpanel_password';
    case DIRECTADMIN_API_TOKEN  = 'directadmin_api_token';
    case DIRECTADMIN_PASSWORD   = 'directadmin_password';
    case SFTP_PASSWORD          = 'sftp_password';

    public function label(): string
    {
        return match($this) {
            self::SSH_KEY               => 'SSH Private Key',
            self::SSH_PASSWORD          => 'SSH Password',
            self::CPANEL_API_TOKEN      => 'cPanel API Token',
            self::CPANEL_PASSWORD       => 'cPanel Username + Password',
            self::DIRECTADMIN_API_TOKEN => 'DirectAdmin API Token',
            self::DIRECTADMIN_PASSWORD  => 'DirectAdmin Username + Password',
            self::SFTP_PASSWORD         => 'SFTP Username + Password',
        };
    }

    /** Returns all auth methods supported by the given driver type. */
    public static function forDriver(DriverType $driver): array
    {
        return match($driver) {
            DriverType::SSH         => [self::SSH_KEY, self::SSH_PASSWORD],
            DriverType::CPANEL      => [self::CPANEL_API_TOKEN, self::CPANEL_PASSWORD],
            DriverType::DIRECTADMIN => [self::DIRECTADMIN_API_TOKEN, self::DIRECTADMIN_PASSWORD],
            DriverType::SFTP        => [self::SFTP_PASSWORD],
            DriverType::DOCKER      => [self::SSH_KEY, self::SSH_PASSWORD],
            DriverType::FAKE        => [self::SSH_KEY],
        };
    }

    /** True when the credential is a private key (not a password or token). */
    public function requiresPrivateKey(): bool
    {
        return $this === self::SSH_KEY;
    }

    /** True when the credential is an API token. */
    public function requiresApiToken(): bool
    {
        return in_array($this, [self::CPANEL_API_TOKEN, self::DIRECTADMIN_API_TOKEN]);
    }
}
