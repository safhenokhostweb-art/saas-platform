<?php

namespace SaaSPlatform\Modules\MotionGraphics\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAuditLoggerContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPermissionGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\SiteResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicDeployment;

final class MotionDeploymentService
{
    public function __construct(
        private readonly PlatformAdminResolverContract $admins,
        private readonly MotionPermissionGatewayContract $permissions,
        private readonly SiteResolverContract $sites,
        private readonly MotionLibraryService $library,
        private readonly MotionAuditLoggerContract $audit,
    ) {}

    public function assign(
        MotionGraphic $motion,
        string $siteIdentifier,
        string $pageIdentifier,
        string $placementIdentifier,
        mixed $actor,
        ?string $sectionIdentifier = null,
        int $sortOrder = 0,
        bool $enabled = true,
        bool $followLatestPublished = true,
        ?int $selectedVersion = null,
    ): MotionGraphicDeployment {
        if (! $this->admins->isPlatformAdmin($actor) || ! $this->permissions->allows($actor, 'motion.deploy')) {
            throw ValidationException::withMessages(['authorization' => 'Platform Admin authorization is required.']);
        }
        if (! $this->sites->exists($siteIdentifier)) {
            throw ValidationException::withMessages(['site' => 'Unknown external site identifier.']);
        }
        if ($motion->ownership_type === MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC
            && $motion->owner_site_identifier !== $siteIdentifier) {
            throw ValidationException::withMessages(['site' => 'Customer-specific motion cannot cross site boundaries.']);
        }
        if ($motion->published_version === null) {
            throw ValidationException::withMessages(['motion' => 'Only a published motion can be prepared for deployment.']);
        }
        if (! $followLatestPublished && $selectedVersion !== $motion->published_version) {
            throw ValidationException::withMessages(['selected_version' => 'Selected version must be the current published version.']);
        }

        $deployment = MotionGraphicDeployment::query()->updateOrCreate([
            'motion_graphic_id' => $motion->id,
            'external_site_identifier' => $siteIdentifier,
            'page_identifier' => $pageIdentifier,
            'placement_identifier' => $placementIdentifier,
        ], [
            'section_identifier' => $sectionIdentifier,
            'sort_order' => max(0, $sortOrder),
            'is_enabled' => $enabled,
            'follow_latest_published' => $followLatestPublished,
            'selected_version' => $followLatestPublished ? null : $selectedVersion,
            'installed_at' => now(),
        ]);
        $this->audit->record('motion.deployment_prepared', [
            'motion_uuid' => $motion->uuid,
            'site_identifier' => $siteIdentifier,
            'actor' => $this->admins->actorIdentifier($actor),
        ]);

        return $deployment;
    }

    public function manifestsFor(string $siteIdentifier, string $pageIdentifier, ?string $placement = null): Collection
    {
        return MotionGraphicDeployment::query()
            ->where('external_site_identifier', $siteIdentifier)
            ->where('page_identifier', $pageIdentifier)
            ->where('is_enabled', true)
            ->when($placement, fn ($query) => $query->where('placement_identifier', $placement))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MotionGraphicDeployment $deployment): array => $this->library->runtimeManifest($deployment));
    }
}
