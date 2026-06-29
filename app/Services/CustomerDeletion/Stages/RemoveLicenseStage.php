<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Models\PlatformLicense;
use App\Models\Site;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use Illuminate\Support\Facades\Log;

/**
 * STAGE 11: REMOVE_LICENSE
 *
 * Revokes the customer's license:
 * - Deletes the PlatformLicense record (invalidates all future API license checks)
 * - Clears the license_key column on the Site record
 *
 * After this stage the customer's application can no longer pass the
 * EnsureLicenseValid middleware — it becomes effectively unusable.
 */
class RemoveLicenseStage extends AbstractDeleteStage
{
    public function name(): string { return 'REMOVE_LICENSE'; }
    public function label(): string { return 'ابطال لایسنس'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 3; }

    public function buildPreSnapshot(DeleteContext $ctx): array
    {
        return [
            'site_id'            => $ctx->siteId,
            'license_key_prefix' => $ctx->licenseKey ? substr($ctx->licenseKey, 0, 8) : null,
        ];
    }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            $revoked = [];

            if ($ctx->licenseKey) {
                // Delete PlatformLicense record(s) for this key
                $deleted = PlatformLicense::where('license_key', $ctx->licenseKey)->delete();
                $revoked[] = "PlatformLicense: {$deleted} records deleted";
            }

            if ($ctx->siteId) {
                $site = Site::find($ctx->siteId);
                if ($site) {
                    $site->update(['license_key' => null]);
                    $revoked[] = "Site #{$ctx->siteId} license_key cleared";
                }
            }

            return DeleteStageResult::success($this->name(), [
                'revoked' => $revoked,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $checks = [];

        if ($ctx->licenseKey) {
            $exists = PlatformLicense::where('license_key', $ctx->licenseKey)->exists();
            $checks[] = ['check' => 'PlatformLicense record deleted', 'passed' => !$exists, 'detail' => substr($ctx->licenseKey, 0, 8) . '...'];
        }

        if ($ctx->siteId) {
            $site = Site::find($ctx->siteId);
            $checks[] = ['check' => 'Site license_key cleared', 'passed' => $site === null || $site->license_key === null, 'detail' => "site_id={$ctx->siteId}"];
        }

        return $checks ?: [['check' => 'License removal', 'passed' => true, 'detail' => 'no license key to revoke']];
    }
}
