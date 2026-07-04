<?php

namespace App\Services\CustomerInstallation\Enums;

enum DeploymentCapability: string
{
    case DOMAIN_MANAGEMENT = 'domain_management';
    case DATABASE_MANAGEMENT = 'database_management';
    case MANUAL_DB_CREDENTIALS = 'manual_db_credentials';
    case FILE_UPLOAD = 'file_upload';
    case FILE_EXTRACT = 'file_extract';
    case SSL_MANAGEMENT = 'ssl_management';
    case CRON_MANAGEMENT = 'cron_management';
    case EMAIL_MANAGEMENT = 'email_management';
    case BACKUP_RESTORE = 'backup_restore';
    case PHP_VERSION = 'php_version';
    case DISK_QUOTA = 'disk_quota';

    /** @return list<self> */
    public static function mandatoryForInstallation(): array
    {
        return [
            self::FILE_UPLOAD,
            self::FILE_EXTRACT,
        ];
    }
}