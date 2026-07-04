<?php

namespace App\Services\CustomerInstallation\Enums;

enum InstallationMode: string
{
    case FULL_SERVER_INSTALL = 'FULL_SERVER_INSTALL';
    case HOSTING_PANEL_INSTALL = 'HOSTING_PANEL_INSTALL';

    public static function forDriver(string $driver): self
    {
        return in_array(strtolower($driver), ['cpanel', 'directadmin', 'shared', 'sftp'], true)
            ? self::HOSTING_PANEL_INSTALL
            : self::FULL_SERVER_INSTALL;
    }
}
