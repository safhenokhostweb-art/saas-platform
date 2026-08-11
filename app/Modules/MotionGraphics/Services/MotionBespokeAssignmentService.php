<?php

namespace App\Modules\MotionGraphics\Services;

use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\Contracts\MotionLicenseGatewayContract;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\MotionBespokeAssignment;
use App\Modules\MotionGraphics\Models\MotionBespokeAssignmentEvent;
use App\Modules\MotionGraphics\Models\MotionBespokeCommission;
use App\Modules\MotionGraphics\Models\MotionBespokePurchase;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;

/**
 * Phase X — the ONLY place a bespoke commission's assignment to its owning
 * site is created, upgraded, rolled back, or revoked. Deliberately never
 * called by OrderFulfillmentService — assignment is always a separate,
 * deliberate Platform-Admin action taken after a real purchase exists,
 * per the task's explicit "must not be directly/automatically activated"
 * rule. Mirrors SiteAssetPackageActivationService's pattern (current
 * pointer + append-only event history, install/upgrade/rollback), applied
 * to a directly-owned bespoke asset rather than a template-bundled one.
 */
final class MotionBespokeAssignmentService extends AbstractMotionAdapter
{
    private const ABILITY_ASSIGN = 'motion.assign_bespoke_commission';

    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionAuthorizationInterface $authorization,
        private readonly MotionLicenseGatewayContract $license,
        private readonly MotionAssetReadContract $assets,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    /**
     * @throws MotionAdapterFailClosedException when not published, not
     *   purchased, the site's base Motion Graphics entitlement is invalid,
     *   or the target version is not currently readable under module policy
     */
    public function assign(MotionBespokeCommission $commission, int $version, MotionAdapterContext $context): MotionBespokeAssignment
    {
        $this->authorizeAssign($commission, $context);

        if ($commission->status !== MotionBespokeCommission::STATUS_PUBLISHED || $commission->asset_id === null) {
            throw new MotionAdapterFailClosedException(
                'commission_not_published',
                'Only a published commission with a real asset reference can be assigned.'
            );
        }

        $hasPurchase = MotionBespokePurchase::query()
            ->where('commission_id', $commission->id)
            ->where('site_id', $commission->site_id)
            ->exists();
        if (! $hasPurchase) {
            throw new MotionAdapterFailClosedException(
                'commission_not_purchased',
                'This site has no recorded purchase for this bespoke commission.'
            );
        }

        $entitlement = $this->license->validateEntitlement((string) $commission->site_id, 'bespoke-assignment');
        if (! $entitlement->allowed) {
            throw new MotionAdapterFailClosedException($entitlement->code, $entitlement->reason);
        }

        $assetModel = $this->assets->findByCanonicalId($commission->asset_id, $version);
        if ($assetModel === null) {
            throw new MotionAdapterFailClosedException(
                'version_not_available',
                'The requested version is not currently readable under module policy.'
            );
        }

        return DB::transaction(function () use ($commission, $version, $context): MotionBespokeAssignment {
            $assignment = MotionBespokeAssignment::query()->where('commission_id', $commission->id)->first();
            $previousVersion = $assignment?->active_version;

            if ($assignment === null) {
                $assignment = MotionBespokeAssignment::query()->create([
                    'commission_id' => $commission->id,
                    'site_id' => $commission->site_id,
                    'asset_id' => $commission->asset_id,
                    'active_version' => $version,
                    'status' => MotionBespokeAssignment::STATUS_ACTIVE,
                    'assigned_by_actor_identifier' => $context->actorId,
                    'assigned_at' => now(),
                ]);
                $eventType = MotionBespokeAssignmentEvent::TYPE_INSTALL;
            } else {
                $assignment->update([
                    'active_version' => $version,
                    'status' => MotionBespokeAssignment::STATUS_ACTIVE,
                    'assigned_by_actor_identifier' => $context->actorId,
                    'assigned_at' => now(),
                ]);
                $eventType = $version === $previousVersion
                    ? MotionBespokeAssignmentEvent::TYPE_INSTALL
                    : MotionBespokeAssignmentEvent::TYPE_UPGRADE;
            }

            MotionBespokeAssignmentEvent::query()->create([
                'assignment_id' => $assignment->id,
                'version' => $version,
                'previous_version' => $previousVersion,
                'event_type' => $eventType,
                'actor_identifier' => $context->actorId,
            ]);

            $this->auditEvents->record(MotionAuditEvents::BESPOKE_ASSIGNED, [
                'site_id' => $commission->site_id,
                'commission_id' => $commission->id,
                'version' => $version,
                'previous_version' => $previousVersion,
                'actor' => $context->actorId,
                'correlation_id' => $context->correlationId,
            ]);

            return $assignment->fresh();
        });
    }

    /**
     * Rolls the site's currently assigned version back to a specific
     * previously-assigned version. Never deletes history; always appends
     * a new `rollback` event.
     */
    public function rollback(MotionBespokeCommission $commission, int $targetVersion, MotionAdapterContext $context): MotionBespokeAssignment
    {
        $this->authorizeAssign($commission, $context);

        $assignment = MotionBespokeAssignment::query()->where('commission_id', $commission->id)->first();
        if ($assignment === null) {
            throw new MotionAdapterFailClosedException('no_active_assignment', 'This commission has no existing assignment to roll back.');
        }

        $wasEverAssigned = MotionBespokeAssignmentEvent::query()
            ->where('assignment_id', $assignment->id)
            ->where('version', $targetVersion)
            ->exists();
        if (! $wasEverAssigned) {
            throw new MotionAdapterFailClosedException(
                'rollback_version_never_assigned',
                'Cannot roll back to a version that was never assigned to this site.'
            );
        }

        $assetModel = $this->assets->findByCanonicalId($commission->asset_id, $targetVersion);
        if ($assetModel === null) {
            throw new MotionAdapterFailClosedException(
                'version_not_available',
                'The requested rollback version is not currently readable under module policy.'
            );
        }

        return DB::transaction(function () use ($assignment, $targetVersion, $commission, $context): MotionBespokeAssignment {
            $previousVersion = $assignment->active_version;
            $assignment->update(['active_version' => $targetVersion, 'status' => MotionBespokeAssignment::STATUS_ACTIVE]);

            MotionBespokeAssignmentEvent::query()->create([
                'assignment_id' => $assignment->id,
                'version' => $targetVersion,
                'previous_version' => $previousVersion,
                'event_type' => MotionBespokeAssignmentEvent::TYPE_ROLLBACK,
                'actor_identifier' => $context->actorId,
            ]);

            $this->auditEvents->record(MotionAuditEvents::BESPOKE_ROLLBACK, [
                'site_id' => $commission->site_id,
                'commission_id' => $commission->id,
                'version' => $targetVersion,
                'previous_version' => $previousVersion,
                'actor' => $context->actorId,
                'correlation_id' => $context->correlationId,
            ]);

            return $assignment->fresh();
        });
    }

    public function revoke(MotionBespokeCommission $commission, MotionAdapterContext $context): MotionBespokeAssignment
    {
        $this->authorizeAssign($commission, $context);

        $assignment = MotionBespokeAssignment::query()->where('commission_id', $commission->id)->first();
        if ($assignment === null) {
            throw new MotionAdapterFailClosedException('no_active_assignment', 'This commission has no existing assignment to revoke.');
        }

        return DB::transaction(function () use ($assignment, $commission, $context): MotionBespokeAssignment {
            $assignment->update(['status' => MotionBespokeAssignment::STATUS_REVOKED]);

            MotionBespokeAssignmentEvent::query()->create([
                'assignment_id' => $assignment->id,
                'version' => $assignment->active_version,
                'previous_version' => $assignment->active_version,
                'event_type' => MotionBespokeAssignmentEvent::TYPE_REVOKE,
                'actor_identifier' => $context->actorId,
            ]);

            $this->auditEvents->record(MotionAuditEvents::BESPOKE_REVOKED, [
                'site_id' => $commission->site_id,
                'commission_id' => $commission->id,
                'actor' => $context->actorId,
                'correlation_id' => $context->correlationId,
            ]);

            return $assignment->fresh();
        });
    }

    private function authorizeAssign(MotionBespokeCommission $commission, MotionAdapterContext $context): \App\Modules\MotionGraphics\DTO\AuthorizationDecision
    {
        $this->ensureEnabled();

        if ((int) $commission->site_id !== (int) $context->siteId) {
            throw new MotionAdapterFailClosedException(
                'tenant_isolation_violation',
                'This commission does not belong to the requested site.'
            );
        }

        $decision = $this->authorization->authorize($context->actorId, self::ABILITY_ASSIGN, 'bespoke-commission', $context->siteId);
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }

        return $decision;
    }
}
