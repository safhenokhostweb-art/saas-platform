<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Models\CustomerInstallation;
use App\Models\Site;
use App\Services\SiteDeletionService;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * STAGE 12: REMOVE_PLATFORM_RECORDS
 *
 * Deletes all platform-side database records for the customer:
 * - Site record (and any cascading relations on it)
 * - Customer record: always kept (ADR-090, owner decision 2026-11-20)
 * - CustomerInstallation metadata (logs are kept as immutable audit trail)
 *   → installation record is marked 'deleted' rather than hard-deleted
 *
 * This is wrapped in a DB transaction so either all platform records are
 * removed or none are.
 */
class RemovePlatformRecordsStage extends AbstractDeleteStage
{
    public function name(): string { return 'REMOVE_PLATFORM_RECORDS'; }
    public function label(): string { return 'حذف رکوردهای پلتفرم'; }
    public function isCritical(): bool { return false; }

    /** ADR-090 — the site row stays in the panel until every earlier stage succeeded. */
    public function requiresCleanRun(): bool { return true; }
    public function estimatedSeconds(): int { return 5; }

    public function buildPreSnapshot(DeleteContext $ctx): array
    {
        return [
            'site_id'         => $ctx->siteId,
            'installation_id' => $ctx->installation->id,
        ];
    }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            $removed = [];

            DB::transaction(function () use ($ctx, &$removed) {
                $siteId = $ctx->siteId;

                if ($siteId) {
                    $site = Site::find($siteId);
                    if ($site) {
                        $customerId = $site->customer_id;
                        // Same semantics as the panel-only delete: cascades,
                        // licence/purchase history detached, installations marked.
                        app(SiteDeletionService::class)->delete($site);
                        $removed[] = "Site #{$siteId} deleted";
                        // ADR-090 (owner decision 2026-11-20): the Customer
                        // record is always kept, even with no remaining sites.
                        if ($customerId) {
                            $removed[] = "Customer #{$customerId} retained";
                        }
                    }
                }

                // Mark the installation as 'deleted' so it appears in audit history
                // but is excluded from active views. We do NOT hard-delete it because
                // the immutable logs are the compliance audit trail.
                $ctx->installation->update([
                    'status'        => 'deleted',
                    'completed_at'  => now(),
                ]);
                $removed[] = "Installation #{$ctx->installation->id} marked deleted";
            });

            return DeleteStageResult::success($this->name(), ['removed' => $removed]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $checks = [];

        if ($ctx->siteId) {
            $siteGone = Site::find($ctx->siteId) === null;
            $checks[] = ['check' => "Site #{$ctx->siteId} deleted", 'passed' => $siteGone, 'detail' => "site_id={$ctx->siteId}"];
        }

        $installStatus = CustomerInstallation::find($ctx->installation->id)?->status;
        $checks[] = ['check' => 'Installation marked deleted', 'passed' => $installStatus === 'deleted', 'detail' => "status={$installStatus}"];

        return $checks;
    }
}
