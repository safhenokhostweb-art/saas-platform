<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Models\PlatformLicense;
use App\Models\Site;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;

/**
 * STAGE: REMOVE_LICENSE
 *
 * Revokes the customer's licences:
 * - every PlatformLicense of the site gets revoked_at = now (all future
 *   license checks fail immediately);
 * - the Site's own license_key column is cleared.
 *
 * platform_licenses stores no plaintext key — only license_key_prefix and
 * license_key_hash (see LicenseServerService::findByKey()) — and each row
 * carries site_id, so the site's licences are selected by site_id. (Before
 * this fix the stage queried a non-existent `license_key` column and failed
 * with SQLSTATE 42S22 for every site that had a licence.)
 *
 * Revoke rather than delete: the rows are removed by the platform_licenses
 * site_id cascade when REMOVE_PLATFORM_RECORDS deletes the site, and until
 * then a failed/retried deletion leaves an auditable, already-dead licence.
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
            if (!$ctx->siteId) {
                return DeleteStageResult::skipped($this->name(), 'شناسهٔ سایت معلوم نیست — لایسنسی برای ابطال وجود ندارد');
            }

            $revoked = PlatformLicense::where('site_id', $ctx->siteId)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $cleared = false;
            $site = Site::find($ctx->siteId);
            if ($site && $site->license_key !== null) {
                $site->update(['license_key' => null]);
                $cleared = true;
            }

            return DeleteStageResult::success($this->name(), [
                'licenses_revoked'         => $revoked,
                'site_license_key_cleared' => $cleared,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        if (!$ctx->siteId) {
            return [['check' => 'License removal', 'passed' => true, 'detail' => 'no site_id']];
        }

        $active = self::activeLicenseCount($ctx->siteId);
        $site   = Site::find($ctx->siteId);

        return [
            ['check' => 'No active PlatformLicense for the site', 'passed' => $active === 0, 'detail' => "{$active} active"],
            ['check' => 'Site license_key cleared', 'passed' => $site === null || $site->license_key === null, 'detail' => "site_id={$ctx->siteId}"],
        ];
    }

    public static function activeLicenseCount(int $siteId): int
    {
        return PlatformLicense::where('site_id', $siteId)->whereNull('revoked_at')->count();
    }
}
