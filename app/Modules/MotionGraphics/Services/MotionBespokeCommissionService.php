<?php

namespace App\Modules\MotionGraphics\Services;

use App\Models\Product;
use App\Models\Site;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\MotionBespokeCommission;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;

/**
 * Phase X — admin management of bespoke/custom Motion commissions:
 * create, advance the business lifecycle (draft -> designing -> review ->
 * approved -> published -> archived), and link a sellable Product. Every
 * method is Platform-Admin-only (motion.manage_bespoke_commission is not
 * granted to a Customer by PermissionGatewayAdapter — see
 * docs/motion-custom-assets/AUDIT_AND_ARCHITECTURE.md §"Who may call
 * what"). Never touches the module's own MotionGraphic/MotionGraphicVersion
 * tables — asset_id here is an opaque reference, set once a designer has
 * actually created the module-side asset.
 */
final class MotionBespokeCommissionService extends AbstractMotionAdapter
{
    private const ABILITY_MANAGE = 'motion.manage_bespoke_commission';

    // Phase 11 — the SAME ability PermissionGatewayAdapter already grants a
    // site-bound Customer for self-service actions on their own site
    // (SiteAssetPackageActivationService::activate()/rollback() use it
    // identically). Deliberately not a new ability string — a customer
    // request creates a draft-only row via requestFromCustomer() below,
    // never touches status/asset/product, so it cannot reach anywhere
    // ABILITY_MANAGE's stricter, Platform-Admin-only surface governs.
    private const ABILITY_REQUEST = 'motion.use';

    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionAuthorizationInterface $authorization,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    public function create(Site $site, string $title, MotionAdapterContext $context, ?string $assetId = null): MotionBespokeCommission
    {
        $this->authorizeManage($context);

        $commission = MotionBespokeCommission::query()->create([
            'site_id' => $site->id,
            'title' => $title,
            'asset_id' => $assetId,
            'status' => MotionBespokeCommission::STATUS_DRAFT,
            'created_by_actor_identifier' => $context->actorId,
        ]);

        $this->auditEvents->record(MotionAuditEvents::BESPOKE_COMMISSION_CREATED, [
            'site_id' => $site->id,
            'commission_id' => $commission->id,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $commission;
    }

    /**
     * Phase 11 — customer-initiated request. Deliberately the ONLY
     * bespoke-commission write path a Customer actor can reach: creates a
     * draft-only row (title + free-text description), scoped to the
     * caller's own site by the same tenant-isolation check every other
     * motion.use call already goes through (AuthorizationDecision denies
     * on any siteId mismatch before this method's body ever runs — see
     * MotionAuthorizationAdapter::authorize()). No status transition, no
     * asset/product linking — those remain exclusively on the
     * ABILITY_MANAGE methods above, unchanged.
     */
    public function requestFromCustomer(Site $site, string $title, ?string $description, MotionAdapterContext $context): MotionBespokeCommission
    {
        $this->ensureEnabled();
        $decision = $this->authorization->authorize($context->actorId, self::ABILITY_REQUEST, 'bespoke-commission', $context->siteId);
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }

        $commission = MotionBespokeCommission::query()->create([
            'site_id' => $site->id,
            'title' => $title,
            'description' => $description,
            'status' => MotionBespokeCommission::STATUS_DRAFT,
            'created_by_actor_identifier' => $context->actorId,
        ]);

        $this->auditEvents->record(MotionAuditEvents::BESPOKE_COMMISSION_CREATED, [
            'site_id' => $site->id,
            'commission_id' => $commission->id,
            'requested_by_customer' => true,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $commission;
    }

    /**
     * @throws MotionAdapterFailClosedException on an invalid transition
     */
    public function transition(MotionBespokeCommission $commission, string $targetStatus, MotionAdapterContext $context): MotionBespokeCommission
    {
        $this->authorizeManage($context);

        $allowed = MotionBespokeCommission::ALLOWED_TRANSITIONS[$commission->status] ?? [];
        if (! in_array($targetStatus, $allowed, true)) {
            throw new MotionAdapterFailClosedException(
                'invalid_bespoke_status_transition',
                "Cannot move commission from [{$commission->status}] to [{$targetStatus}]."
            );
        }

        $previousStatus = $commission->status;
        $commission->status = $targetStatus;
        if ($targetStatus === MotionBespokeCommission::STATUS_PUBLISHED) {
            $commission->published_at = now();
        }
        $commission->save();

        $this->auditEvents->record(MotionAuditEvents::BESPOKE_COMMISSION_TRANSITIONED, [
            'site_id' => $commission->site_id,
            'commission_id' => $commission->id,
            'previous_status' => $previousStatus,
            'new_status' => $targetStatus,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $commission->refresh();
    }

    /**
     * Attaches the module-side canonical asset reference, once a designer
     * has actually created it there. Only permitted before publication —
     * a published commission's asset identity is not meant to move.
     */
    public function attachAsset(MotionBespokeCommission $commission, string $assetId, MotionAdapterContext $context): MotionBespokeCommission
    {
        $this->authorizeManage($context);

        if (in_array($commission->status, [MotionBespokeCommission::STATUS_PUBLISHED, MotionBespokeCommission::STATUS_ARCHIVED], true)) {
            throw new MotionAdapterFailClosedException(
                'commission_asset_locked',
                'A published or archived commission cannot have its asset reference changed.'
            );
        }

        $commission->update(['asset_id' => $assetId]);

        return $commission->refresh();
    }

    /**
     * Links (or replaces) the sellable Product row for this commission.
     * The Product must be a real motion_asset-type Product; no schema
     * change on `products` — the link lives here, on our own table.
     */
    public function linkProduct(MotionBespokeCommission $commission, Product $product, MotionAdapterContext $context): MotionBespokeCommission
    {
        $this->authorizeManage($context);

        if ($product->type !== Product::TYPE_MOTION_ASSET) {
            throw new InvalidArgumentException('Only a motion_asset-type Product can be linked to a bespoke commission.');
        }

        $commission->update(['product_id' => $product->id]);

        return $commission->refresh();
    }

    /**
     * @return Collection<int, Site>
     */
    public function sitesUsing(MotionBespokeCommission $commission, MotionAdapterContext $context): Collection
    {
        $this->authorizeManage($context);

        $assignment = $commission->assignment;
        if ($assignment === null || $assignment->status !== \App\Modules\MotionGraphics\Models\MotionBespokeAssignment::STATUS_ACTIVE) {
            return collect();
        }

        return collect([$assignment->site])->filter();
    }

    private function authorizeManage(MotionAdapterContext $context): void
    {
        $this->ensureEnabled();
        $decision = $this->authorization->authorize($context->actorId, self::ABILITY_MANAGE, 'bespoke-commission', $context->siteId);
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }
    }
}
