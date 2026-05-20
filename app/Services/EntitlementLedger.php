<?php
namespace App\Services;

use App\Models\Module;
use App\Models\ModuleEntitlement;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 5.1 / P1-1 — Customer Module Entitlements Ledger service.
 *
 * Source of truth for the *audit history* of module access:
 *   • grant()         records a new entitlement (idempotent — returns the
 *                     existing active row if one already exists for the
 *                     same site/module pair, and merges metadata).
 *   • revoke()        marks an active entitlement as revoked with explicit
 *                     attribution (revoked_by, revoke_reason).
 *   • recordSync()    used by the backfill command to recreate a historical
 *                     entitlement from existing site_modules / purchases /
 *                     licenses without altering anything else.
 *   • currentEntitlement() returns the live active row (if any) for a
 *                     given site + module.
 *
 * IMPORTANT — Phase A scope:
 *   • This service NEVER decides run-time module visibility on its own.
 *     The runtime gate remains site_modules.is_active + valid license
 *     (ModuleLicenseService / SiteHelper). The ledger only records history.
 *   • Every revoke is explicit. Nothing in this class auto-revokes anything
 *     when a related row (purchase, license, site_module) is removed.
 *
 * Safety:
 *   • All operations are guarded by Schema::hasTable('module_entitlements')
 *     so that code running before the migration is applied does not break.
 *   • All writes happen inside a transaction. Failures are logged and the
 *     caller receives a null / false back; no exception leaks unless DB
 *     is hard-broken.
 *
 * Wave 8.0b — Activity audit for grant/revoke is emitted only from this
 * ledger (single source of truth). One activity_logs row per new grant
 * (not on idempotent metadata-merge) and one per successful revoke.
 */
class EntitlementLedger
{
    /**
     * Grant a new entitlement (or return existing active one).
     *
     * @param  array{
     *     granted_by?: int|null,
     *     granted_at?: \DateTimeInterface|string|null,
     *     invoice_id?: int|null,
     *     invoice_item_id?: int|null,
     *     module_purchase_id?: int|null,
     *     module_license_id?: int|null,
     *     metadata?: array<string,mixed>,
     *     reason?: string|null,
     * }  $context
     */
    public function grant(
        Site $site,
        Module $module,
        string $source,
        array $context = []
    ): ?ModuleEntitlement {
        if (! $this->tableReady()) {
            return null;
        }

        if (! in_array($source, $this->allowedSources(), true)) {
            Log::warning('EntitlementLedger::grant called with unknown source', [
                'source' => $source,
                'site_id' => $site->id,
                'module_id' => $module->id,
            ]);

            return null;
        }

        try {
            /** @var array{model: ModuleEntitlement, was_new: bool}|null $payload */
            $payload = DB::transaction(function () use ($site, $module, $source, $context) {
                $existing = ModuleEntitlement::query()
                    ->where('site_id', $site->id)
                    ->where('module_id', $module->id)
                    ->where('status', ModuleEntitlement::STATUS_ACTIVE)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $mergedMetadata = array_merge(
                        is_array($existing->metadata) ? $existing->metadata : [],
                        is_array($context['metadata'] ?? null) ? $context['metadata'] : [],
                        ['last_grant_attempt' => [
                            'source' => $source,
                            'at' => now()->toIso8601String(),
                            'by' => $context['granted_by'] ?? Auth::id(),
                        ]],
                    );
                    $existing->metadata = $mergedMetadata;
                    $existing->save();

                    return ['model' => $existing, 'was_new' => false];
                }

                $grantedAt = $this->parseTimestamp($context['granted_at'] ?? null) ?? now();

                $created = ModuleEntitlement::create([
                    'site_id' => $site->id,
                    'customer_id' => $site->customer_id,
                    'module_id' => $module->id,
                    'module_slug' => $module->slug,
                    'source' => $source,
                    'status' => ModuleEntitlement::STATUS_ACTIVE,
                    'granted_at' => $grantedAt,
                    'granted_by' => $context['granted_by'] ?? Auth::id(),
                    'invoice_id' => $context['invoice_id'] ?? null,
                    'invoice_item_id' => $context['invoice_item_id'] ?? null,
                    'module_purchase_id' => $context['module_purchase_id'] ?? null,
                    'module_license_id' => $context['module_license_id'] ?? null,
                    'metadata' => $context['metadata'] ?? null,
                ]);

                return ['model' => $created, 'was_new' => true];
            });

            if ($payload !== null && $payload['was_new']) {
                $this->logEntitlementGranted($site, $module, $payload['model'], $source, $context);
            }

            return $payload['model'];
        } catch (\Throwable $e) {
            Log::error('EntitlementLedger::grant failed', [
                'site_id' => $site->id,
                'module_id' => $module->id,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Record a historical entitlement from backfill data.
     *
     * Same semantics as grant() but accepts an explicit granted_at and
     * tolerates a null current user (Auth::id() may be null in console).
     */
    public function recordSync(
        Site $site,
        Module $module,
        string $source,
        \DateTimeInterface|string $grantedAt,
        array $context = []
    ): ?ModuleEntitlement {
        return $this->grant($site, $module, $source, array_merge($context, [
            'granted_at' => $grantedAt,
        ]));
    }

    /**
     * Revoke a specific active entitlement. Returns true on success.
     *
     * Every revoke MUST carry:
     *   • $revokedBy — the admin user id (or null for system, used only for
     *     migrations or explicit admin actions logged elsewhere).
     *   • $reason    — stored as `unspecified` when missing (Wave 8.0b audit).
     */
    public function revoke(
        int $entitlementId,
        ?int $revokedBy,
        ?string $reason
    ): bool {
        if (! $this->tableReady()) {
            return false;
        }

        try {
            $logSubject = null;

            $ok = (bool) DB::transaction(function () use ($entitlementId, $revokedBy, $reason, &$logSubject) {
                /** @var ModuleEntitlement|null $row */
                $row = ModuleEntitlement::query()
                    ->where('id', $entitlementId)
                    ->where('status', ModuleEntitlement::STATUS_ACTIVE)
                    ->lockForUpdate()
                    ->first();

                if ($row === null) {
                    return false;
                }

                $finalReason = ($reason !== null && trim((string) $reason) !== '')
                    ? trim((string) $reason)
                    : 'unspecified';

                $row->status = ModuleEntitlement::STATUS_REVOKED;
                $row->revoked_at = now();
                $row->revoked_by = $revokedBy ?? Auth::id();
                $row->revoke_reason = $finalReason;
                if (! $row->save()) {
                    return false;
                }

                $logSubject = $row->fresh();

                return true;
            });

            if ($ok && $logSubject instanceof ModuleEntitlement) {
                $this->logEntitlementRevoked($logSubject);
            }

            return $ok;
        } catch (\Throwable $e) {
            Log::error('EntitlementLedger::revoke failed', [
                'entitlement_id' => $entitlementId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Convenience wrapper to revoke the (single) active entitlement for a
     * site/module pair. Used by admin tooling that does not know the
     * underlying ledger id.
     */
    public function revokeBySiteModule(
        int $siteId,
        int $moduleId,
        ?int $revokedBy,
        ?string $reason
    ): bool {
        if (! $this->tableReady()) {
            return false;
        }
        $active = ModuleEntitlement::query()
            ->where('site_id', $siteId)
            ->where('module_id', $moduleId)
            ->where('status', ModuleEntitlement::STATUS_ACTIVE)
            ->first();
        if ($active === null) {
            return false;
        }

        return $this->revoke($active->id, $revokedBy, $reason);
    }

    public function currentEntitlement(Site $site, Module $module): ?ModuleEntitlement
    {
        if (! $this->tableReady()) {
            return null;
        }

        return ModuleEntitlement::query()
            ->where('site_id', $site->id)
            ->where('module_id', $module->id)
            ->where('status', ModuleEntitlement::STATUS_ACTIVE)
            ->first();
    }

    /**
     * @return array<int, ModuleEntitlement>
     */
    public function activeForSite(Site $site): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        return ModuleEntitlement::query()
            ->where('site_id', $site->id)
            ->where('status', ModuleEntitlement::STATUS_ACTIVE)
            ->orderBy('granted_at')
            ->get()
            ->all();
    }

    /**
     * @return array<int, ModuleEntitlement>
     */
    public function historyForSite(Site $site): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        return ModuleEntitlement::query()
            ->where('site_id', $site->id)
            ->orderBy('granted_at')
            ->get()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function allowedSources(): array
    {
        return [
            ModuleEntitlement::SOURCE_PURCHASE,
            ModuleEntitlement::SOURCE_LICENSE,
            ModuleEntitlement::SOURCE_ADMIN_GRANT,
            ModuleEntitlement::SOURCE_FREE_ASSIGN,
            ModuleEntitlement::SOURCE_SEED,
            ModuleEntitlement::SOURCE_IMPORT,
            ModuleEntitlement::SOURCE_SYNC,
        ];
    }

    protected function tableReady(): bool
    {
        return Schema::hasTable('module_entitlements');
    }

    protected function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($value));
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function logEntitlementGranted(
        Site $site,
        Module $module,
        ModuleEntitlement $entitlement,
        string $source,
        array $context
    ): void {
        $reason = $context['reason'] ?? null;
        if ($reason === null || trim((string) $reason) === '') {
            $reason = $source;
        }

        ActivityLogger::log(
            'entitlement_granted',
            'Entitlement granted to tenant',
            $site,
            [
                'plan_id' => $module->id,
                'tenant_id' => $site->id,
                'granted_by' => $context['granted_by'] ?? Auth::id(),
                'reason' => $reason,
                'entitlement_id' => $entitlement->id,
                'module_slug' => $module->slug,
            ]
        );
    }

    protected function logEntitlementRevoked(ModuleEntitlement $entitlement): void
    {
        ActivityLogger::log(
            'entitlement_revoked',
            'Entitlement revoked',
            $entitlement,
            [
                'plan_id' => $entitlement->module_id,
                'tenant_id' => $entitlement->site_id,
                'revoked_by' => $entitlement->revoked_by,
                'revoke_reason' => $entitlement->revoke_reason ?? 'unspecified',
            ]
        );
    }
}
