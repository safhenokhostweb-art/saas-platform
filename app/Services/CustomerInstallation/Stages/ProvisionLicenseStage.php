<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Models\Customer;
use App\Models\Site;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\LicenseServerService;

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

            // Find or create the Customer by mobile phone
            $customer = Customer::firstOrCreate(
                ['mobile' => $req->phone],
                ['name'   => $req->ownerName]
            );

            // Create or find the Site record using correct column names
            $site = Site::updateOrCreate(
                ['domain' => $req->domain],
                [
                    'customer_id'      => $customer->id,
                    'title'            => $req->company,
                    'is_active'        => true,
                    'lifecycle_status' => Site::LIFECYCLE_ACTIVE,
                ]
            );

            // Link installation to site
            $ctx->installation->update(['site_id' => $site->id]);

            // Issue the license via LicenseServerService so that a PlatformLicense record
            // is created with the correct HMAC-SHA256 hash. Without this record the
            // customer-site's EnsureLicenseValid middleware gets "invalid_key" from the
            // license validate API and the admin panel becomes inaccessible.
            $licenseKey = app(LicenseServerService::class)->issue(
                $site,
                $req->domain,
                null,          // no expiry by default
                'CIE-INSTALL'  // product SKU for CIE-provisioned installs
            );

            // Also store the key on the Site record for quick cross-reference.
            $site->update(['license_key' => $licenseKey]);

            // Update .env LICENSE_KEY placeholder on the remote server
            $safeWebRoot = escapeshellarg($ctx->webRoot());
            $envPath     = $ctx->webRoot() . '/.env';
            $ctx->connection->execute(
                "sed -i 's/__LICENSE_KEY__/" . addslashes($licenseKey) . "/' " . escapeshellarg($envPath)
            );

            // Verify the key was written correctly
            $verifyResult = $ctx->connection->execute(
                "grep -q '^LICENSE_KEY=CIE-' " . escapeshellarg($envPath) . " && echo ok || echo missing"
            );

            if (str_contains($verifyResult->stdout, 'missing')) {
                return StageResult::failure(
                    $this->name(),
                    'LICENSE_KEY placeholder replacement failed — .env may be malformed',
                    $this->elapsed()
                );
            }

            return StageResult::success($this->name(), [
                'site_id'            => $site->id,
                'license_key_prefix' => substr($licenseKey, 0, 8),
                'domain'             => $req->domain,
                'license_stored'     => true,
            ], [
                'site_id' => $site->id,
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
