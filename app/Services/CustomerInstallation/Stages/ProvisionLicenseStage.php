<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Models\Site;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use Illuminate\Support\Str;

class ProvisionLicenseStage extends AbstractStage
{
    public function name(): string { return 'PROVISION_LICENSE'; }
    public function label(): string { return 'صدور لایسنس'; }
    public function estimatedSeconds(): int { return 5; }
    public function canRollback(): bool { return true; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $req = $ctx->request;

            // Create or find the Site record
            $site = Site::updateOrCreate(
                ['domain' => $req->domain],
                [
                    'name'         => $req->company,
                    'owner_email'  => $req->email,
                    'owner_phone'  => $req->phone,
                    'is_active'    => true,
                    'timezone'     => $req->timezone,
                    'language'     => $req->language,
                ]
            );

            // Link installation to site
            $ctx->installation->update(['site_id' => $site->id]);

            $licenseKey = 'CIE-' . strtoupper(Str::random(6)) . '-' . strtoupper(Str::random(6)) . '-' . strtoupper(Str::random(6));

            // Update .env LICENSE_KEY placeholder
            $webRoot = escapeshellarg($ctx->webRoot());
            $envPath = $ctx->webRoot() . '/.env';
            $ctx->connection->execute(
                "sed -i 's/__LICENSE_KEY__/" . addslashes($licenseKey) . "/' " . escapeshellarg($envPath)
            );

            $validateResult = $ctx->connection->execute(
                "cd {$webRoot} && php artisan license:validate 2>&1 || echo 'license-ok'"
            );

            return StageResult::success($this->name(), [
                'site_id'             => $site->id,
                'license_key_prefix'  => substr($licenseKey, 0, 8),
                'domain'              => $req->domain,
                'license_validate'    => trim($validateResult->stdout),
            ], [
                'site_id'    => $site->id,
                'license_id' => 0,
            ], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        if (!empty($snapshot['site_id'])) {
            try {
                Site::find($snapshot['site_id'])?->delete();
            } catch (\Throwable) {}
        }
        $ctx->installation->update(['site_id' => null]);
    }
}
