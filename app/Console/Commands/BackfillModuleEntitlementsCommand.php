<?php
namespace App\Console\Commands;

use App\Models\Module;
use App\Models\ModuleEntitlement;
use App\Models\ModuleLicense;
use App\Models\ModulePurchase;
use App\Models\Site;
use App\Services\EntitlementLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 5.1 / P1-1 — backfill module_entitlements from existing data.
 *
 * Order of operations (most authoritative source last so that later
 * passes can enrich a row's metadata without creating duplicates):
 *
 *   1. site_modules (is_active = true)          → source = seed
 *   2. site_module_purchases (any row)          → source = purchase
 *   3. module_licenses (status = active)        → source = license
 *
 * Idempotency:
 *   - For each (site, module) pair, only one active entitlement row is
 *     created. Subsequent passes call EntitlementLedger::grant() which
 *     merges metadata into the existing active row.
 *   - Safe to re-run any number of times.
 *
 * --dry-run prints what would happen without writing.
 */
class BackfillModuleEntitlementsCommand extends Command
{
    protected $signature = 'entitlements:backfill
                            {--dry-run : Print actions without writing anything}
                            {--site= : Restrict backfill to a single site id}';

    protected $description = 'Wave 5.1 (P1-1): backfill module_entitlements from site_modules, purchases and licenses (idempotent)';

    public function handle(EntitlementLedger $ledger): int
    {
        if (! Schema::hasTable('module_entitlements')) {
            $this->error('module_entitlements table does not exist. Run `php artisan migrate` first.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $siteFilter = $this->option('site') ? (int) $this->option('site') : null;

        $counters = [
            'site_modules' => 0,
            'purchases' => 0,
            'licenses' => 0,
            'skipped_missing_module' => 0,
            'skipped_missing_site' => 0,
        ];

        $this->info($dryRun ? '[DRY RUN] No rows will be written.' : 'Running backfill (writes enabled)…');
        if ($siteFilter) {
            $this->line("Restricted to site id = {$siteFilter}.");
        }

        // ---------- Pass 1: site_modules (is_active = true) ----------
        if (Schema::hasTable('site_modules')) {
            $query = DB::table('site_modules')
                ->where('is_active', true)
                ->select(['site_id', 'module_id', 'activated_at', 'created_at']);
            if ($siteFilter) {
                $query->where('site_id', $siteFilter);
            }
            $rows = $query->get();
            $this->line("Pass 1 — site_modules: {$rows->count()} active rows");
            foreach ($rows as $row) {
                $site = Site::find($row->site_id);
                $module = Module::find($row->module_id);
                if (! $site) { $counters['skipped_missing_site']++; continue; }
                if (! $module) { $counters['skipped_missing_module']++; continue; }

                $grantedAt = $row->activated_at ?? $row->created_at ?? now();
                if ($dryRun) {
                    $this->line("  [DRY] grant site={$site->id} module={$module->slug} src=seed at={$grantedAt}");
                } else {
                    $ledger->recordSync($site, $module, ModuleEntitlement::SOURCE_SEED, $grantedAt, [
                        'metadata' => ['backfill_pass' => 'site_modules'],
                    ]);
                }
                $counters['site_modules']++;
            }
        }

        // ---------- Pass 2: site_module_purchases ----------
        if (Schema::hasTable('site_module_purchases')) {
            $query = ModulePurchase::query();
            if ($siteFilter) {
                $query->where('site_id', $siteFilter);
            }
            $rows = $query->get();
            $this->line("Pass 2 — site_module_purchases: {$rows->count()} rows");
            foreach ($rows as $purchase) {
                $site = Site::find($purchase->site_id);
                $module = Module::find($purchase->module_id);
                if (! $site) { $counters['skipped_missing_site']++; continue; }
                if (! $module) { $counters['skipped_missing_module']++; continue; }

                if ($dryRun) {
                    $this->line("  [DRY] grant site={$site->id} module={$module->slug} src=purchase invoice={$purchase->invoice_id}");
                } else {
                    $ledger->recordSync(
                        $site,
                        $module,
                        ModuleEntitlement::SOURCE_PURCHASE,
                        $purchase->purchased_at ?? $purchase->created_at ?? now(),
                        [
                            'granted_by' => $purchase->granted_by,
                            'invoice_id' => $purchase->invoice_id,
                            'invoice_item_id' => $purchase->invoice_item_id,
                            'module_purchase_id' => $purchase->id,
                            'metadata' => [
                                'backfill_pass' => 'site_module_purchases',
                                'amount_irr' => $purchase->amount_irr,
                            ],
                        ]
                    );
                }
                $counters['purchases']++;
            }
        }

        // ---------- Pass 3: module_licenses (status = active) ----------
        if (Schema::hasTable('module_licenses')) {
            $query = ModuleLicense::query()->where('status', 'active');
            if ($siteFilter) {
                $query->where('site_id', $siteFilter);
            }
            $rows = $query->get();
            $this->line("Pass 3 — module_licenses (active): {$rows->count()} rows");
            foreach ($rows as $license) {
                $site = Site::find($license->site_id);
                $module = Module::find($license->module_id);
                if (! $site) { $counters['skipped_missing_site']++; continue; }
                if (! $module) { $counters['skipped_missing_module']++; continue; }

                if ($dryRun) {
                    $this->line("  [DRY] grant site={$site->id} module={$module->slug} src=license expires={$license->expires_at}");
                } else {
                    $ledger->recordSync(
                        $site,
                        $module,
                        ModuleEntitlement::SOURCE_LICENSE,
                        $license->purchased_at ?? $license->created_at ?? now(),
                        [
                            'granted_by' => $license->granted_by,
                            'invoice_id' => $license->invoice_id,
                            'invoice_item_id' => $license->invoice_item_id,
                            'module_purchase_id' => $license->module_purchase_id,
                            'module_license_id' => $license->id,
                            'metadata' => [
                                'backfill_pass' => 'module_licenses',
                                'license_type' => $license->license_type,
                                'expires_at' => optional($license->expires_at)->toIso8601String(),
                            ],
                        ]
                    );
                }
                $counters['licenses']++;
            }
        }

        $this->info('Done.');
        $this->table(['metric', 'count'], collect($counters)->map(fn($v, $k) => [$k, $v])->values()->all());
        if ($dryRun) {
            $this->warn('Dry run only — no changes were persisted.');
        }
        return self::SUCCESS;
    }
}
