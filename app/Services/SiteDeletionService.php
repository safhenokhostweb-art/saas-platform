<?php

namespace App\Services;

use App\Models\CustomerInstallation;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a site row and everything that only exists for it, in one
 * transaction (all or nothing).
 *
 * - Tables that cascade from `sites` (modules, settings, notifications, ...)
 *   are removed by their foreign keys.
 * - Licence-ledger, motion purchase/commission/assignment and asset-package
 *   history is kept, detached (site_id SET NULL; migration
 *   2026_11_17_100000_detach_site_history_on_site_delete).
 * - customer_installations has no foreign key to sites. Its rows are marked
 *   `deleted` rather than removed, so the installation audit trail remains.
 */
class SiteDeletionService
{
    /** @return array{installations_marked:int} */
    public function delete(Site $site): array
    {
        return DB::transaction(function () use ($site): array {
            $marked = CustomerInstallation::where('site_id', $site->id)
                ->where('status', '!=', 'deleted')
                ->update(['status' => 'deleted', 'completed_at' => now()]);

            $site->delete();

            return ['installations_marked' => $marked];
        });
    }
}
