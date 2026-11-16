<?php

namespace App\Modules\MotionGraphics\Services;

use App\Models\Site;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\MotionSiteAssetGrant;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;

/**
 * ADR-086: the central panel's two catalog-wide controls, and the only
 * writer of both.
 *
 * - Availability switch (`runtime_enabled`): turning a motion off removes it
 *   from every runtime manifest on the next fetch (MotionCustomerRuntimeService
 *   and BespokeAssignedAssets skip it before anything that could fail a
 *   manifest). Versions, package bindings and grants are untouched, so
 *   turning it back on restores exactly what each site had.
 * - Private-motion grants (motion_site_asset_grants): the per-site, per-asset
 *   entitlement MotionAssetLicensePolicy requires for a `private` motion.
 *   Public motions need none, and customer_specific (exclusive) motions are
 *   managed by ownership and bespoke assignments, so both are refused here.
 *
 * Every change is audited through the platform activity log.
 */
final class MotionCatalogAdminService
{
    public function __construct(
        private readonly MotionAuditEvents $auditEvents,
    ) {}

    public function setRuntimeEnabled(MotionGraphic $motion, bool $enabled, string $actorId): MotionGraphic
    {
        if ($motion->isRuntimeEnabled() === $enabled) {
            return $motion;
        }

        $motion->forceFill([
            'runtime_enabled' => $enabled,
            'runtime_disabled_at' => $enabled ? null : now(),
            'runtime_disabled_by_actor_identifier' => $enabled ? null : $actorId,
        ])->save();

        $this->auditEvents->record($enabled ? MotionAuditEvents::CATALOG_ASSET_ENABLED : MotionAuditEvents::CATALOG_ASSET_DISABLED, [
            'asset_id' => $motion->uuid,
            'slug' => $motion->slug,
            'actor' => $actorId,
        ]);

        return $motion->refresh();
    }

    /**
     * Grants (or re-activates) one private motion for one site.
     *
     * @throws MotionAdapterFailClosedException for a public, exclusive or unpublished motion
     */
    public function grant(
        MotionGraphic $motion,
        Site $site,
        string $source,
        ?string $reference,
        ?string $note,
        string $actorId,
    ): MotionSiteAssetGrant {
        if ($motion->ownership_type !== MotionGraphic::OWNERSHIP_GLOBAL) {
            throw new MotionAdapterFailClosedException('grant_not_applicable', 'Exclusive motions are assigned through their bespoke commission, not a grant.');
        }
        if ($motion->visibility() !== MotionGraphic::VISIBILITY_PRIVATE) {
            throw new MotionAdapterFailClosedException('grant_not_applicable', 'Public motions reach every entitled site and need no grant.');
        }
        if ($motion->published_version === null) {
            throw new MotionAdapterFailClosedException('asset_not_published', 'Only a published motion can be granted.');
        }
        if (! in_array($source, MotionSiteAssetGrant::SOURCES, true)) {
            throw new MotionAdapterFailClosedException('grant_source_invalid', 'Unknown grant source.');
        }

        return DB::transaction(function () use ($motion, $site, $source, $reference, $note, $actorId): MotionSiteAssetGrant {
            $grant = MotionSiteAssetGrant::query()
                ->where('site_id', $site->id)
                ->where('asset_id', $motion->uuid)
                ->lockForUpdate()
                ->first() ?? new MotionSiteAssetGrant(['site_id' => $site->id, 'asset_id' => $motion->uuid]);

            $grant->fill([
                'status' => MotionSiteAssetGrant::STATUS_ACTIVE,
                'source' => $source,
                'reference' => $reference,
                'note' => $note,
                'granted_by_actor_identifier' => $actorId,
                'granted_at' => now(),
                'revoked_by_actor_identifier' => null,
                'revoked_at' => null,
            ])->save();

            $this->auditEvents->record(MotionAuditEvents::CATALOG_ASSET_GRANTED, [
                'site_id' => $site->id,
                'asset_id' => $motion->uuid,
                'slug' => $motion->slug,
                'source' => $source,
                'actor' => $actorId,
            ]);

            return $grant->refresh();
        });
    }

    public function revoke(MotionSiteAssetGrant $grant, string $actorId): MotionSiteAssetGrant
    {
        if ($grant->status === MotionSiteAssetGrant::STATUS_REVOKED) {
            return $grant;
        }

        $grant->forceFill([
            'status' => MotionSiteAssetGrant::STATUS_REVOKED,
            'revoked_by_actor_identifier' => $actorId,
            'revoked_at' => now(),
        ])->save();

        $this->auditEvents->record(MotionAuditEvents::CATALOG_ASSET_GRANT_REVOKED, [
            'site_id' => $grant->site_id,
            'asset_id' => $grant->asset_id,
            'actor' => $actorId,
        ]);

        return $grant->refresh();
    }
}
