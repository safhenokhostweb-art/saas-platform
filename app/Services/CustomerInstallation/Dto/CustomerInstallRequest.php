<?php

namespace App\Services\CustomerInstallation\Dto;

class CustomerInstallRequest
{
    public function __construct(
        public readonly string  $company,
        public readonly string  $ownerName,
        public readonly string  $email,
        public readonly string  $phone,
        public readonly string  $language,
        public readonly string  $timezone,
        public readonly string  $currency,
        public readonly string  $domain,
        public readonly string  $licenseType,
        public readonly string  $themeSlug,
        public readonly array   $modules,
        public readonly string  $backupPolicy,
        public readonly array   $smtp,
        public readonly bool    $sslEnabled,
        public readonly int     $remoteServerId,
        public readonly string  $installMode,
        public readonly ?int    $sourceInstallationId = null,
        public readonly ?string $restoreBackupKey = null,
        // Unified onboarding wizard (ADR-088). All optional: a payload from
        // any earlier caller omits them and the pipeline behaves as before.
        public readonly ?int    $customerId = null,
        public readonly ?string $businessVertical = null,
        /** @var list<int> license-only module ids (kits) to license and activate */
        public readonly array   $kitModuleIds = [],
        public readonly ?int    $themeId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            company:              $data['company'],
            ownerName:            $data['owner_name'],
            email:                $data['email'],
            phone:                $data['phone'],
            language:             $data['language'] ?? 'fa',
            timezone:             $data['timezone'] ?? 'Asia/Tehran',
            currency:             $data['currency'] ?? 'IRR',
            domain:               $data['domain'],
            licenseType:          $data['license_type'] ?? 'basic',
            themeSlug:            $data['theme_slug'] ?? '',
            modules:              $data['modules'] ?? [],
            backupPolicy:         $data['backup_policy'] ?? 'daily',
            smtp:                 $data['smtp'] ?? [],
            sslEnabled:           (bool) ($data['ssl_enabled'] ?? true),
            remoteServerId:       (int) $data['remote_server_id'],
            installMode:          $data['install_mode'] ?? 'MODE_FRESH',
            sourceInstallationId: isset($data['source_installation_id']) ? (int) $data['source_installation_id'] : null,
            restoreBackupKey:     $data['restore_backup_key'] ?? null,
            customerId:           !empty($data['customer_id']) ? (int) $data['customer_id'] : null,
            businessVertical:     isset($data['business_vertical']) && $data['business_vertical'] !== '' ? (string) $data['business_vertical'] : null,
            kitModuleIds:         array_values(array_unique(array_map('intval', (array) ($data['kit_module_ids'] ?? [])))),
            themeId:              !empty($data['theme_id']) ? (int) $data['theme_id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'company'               => $this->company,
            'owner_name'            => $this->ownerName,
            'email'                 => $this->email,
            'phone'                 => $this->phone,
            'language'              => $this->language,
            'timezone'              => $this->timezone,
            'currency'              => $this->currency,
            'domain'                => $this->domain,
            'license_type'          => $this->licenseType,
            'theme_slug'            => $this->themeSlug,
            'modules'               => $this->modules,
            'backup_policy'         => $this->backupPolicy,
            'smtp'                  => $this->smtp,
            'ssl_enabled'           => $this->sslEnabled,
            'remote_server_id'      => $this->remoteServerId,
            'install_mode'          => $this->installMode,
            'source_installation_id'=> $this->sourceInstallationId,
            'restore_backup_key'    => $this->restoreBackupKey,
            'customer_id'           => $this->customerId,
            'business_vertical'     => $this->businessVertical,
            'kit_module_ids'        => $this->kitModuleIds,
            'theme_id'              => $this->themeId,
        ];
    }
}
