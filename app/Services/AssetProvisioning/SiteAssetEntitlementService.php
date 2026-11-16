<?php

namespace App\Services\AssetProvisioning;

use App\Models\PlatformAssetPolicy;
use App\Models\Site;
use App\Models\SiteAssetGrant;
use App\Models\Theme;
use App\Services\ModuleEcosystem\Contracts\ModuleEntitlementResolverContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessCatalog;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessRule;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;
use SaaSPlatform\ThemeRuntime\Access\BusinessVerticals;
use SaaSPlatform\ThemeRuntime\Access\SiteAssetEntitlements;

/**
 * ADR-087: the platform's authority over who may use which platform asset
 * (library templates, widgets). It builds a site's SiteAssetEntitlements
 * from the platform's own state, never from anything the tenant sends:
 *   - entitlement keys = the canonical module projection
 *     (EntitlementResolver::projectionForSite: active AND entitled);
 *   - the site's business vertical;
 *   - its active per-asset grants;
 *   - the platform's per-asset policy overrides.
 * It is also the only writer of grants, overrides and the vertical, and
 * audits every write. The same object answers the provisioning API, the
 * platform canvas and the central panel.
 */
class SiteAssetEntitlementService
{
    public const EVENT_GRANTED = 'asset_access.grant.granted';

    public const EVENT_REVOKED = 'asset_access.grant.revoked';

    public const EVENT_VERTICAL_CHANGED = 'asset_access.site.vertical_changed';

    public const EVENT_OVERRIDE_SET = 'asset_access.policy.override_set';

    public const EVENT_OVERRIDE_CLEARED = 'asset_access.policy.override_cleared';

    public function __construct(
        protected ModuleEntitlementResolverContract $entitlements,
        protected ActivityLoggerInterface $logger,
    ) {}

    public function forSite(Site $site): SiteAssetEntitlements
    {
        if (!$site->is_active) {
            // An inactive site keeps rendering what it has but unlocks nothing.
            return SiteAssetEntitlements::make(null, [], [], $this->overrides(), now()->toIso8601String());
        }

        $grants = Schema::hasTable('site_asset_grants')
            ? SiteAssetGrant::query()->where('site_id', $site->id)->active()->get()->map->ref()->all()
            : [];

        return SiteAssetEntitlements::make(
            $site->business_vertical,
            $this->entitlements->projectionForSite($site),
            $grants,
            $this->overrides(),
            now()->toIso8601String(),
        );
    }

    /**
     * The platform canvas: a theme a site owns is edited under that site's
     * entitlements; a global catalog theme is the platform's own design
     * surface (everything usable, restricted items badged).
     */
    public function forTheme(Theme $theme): SiteAssetEntitlements
    {
        $site = $theme->site_id ? Site::query()->find($theme->site_id) : null;

        return $site ? $this->forSite($site) : SiteAssetEntitlements::platformDesigner();
    }

    /** @return array<string, array> ref => rule array */
    public function overrides(): array
    {
        if (!Schema::hasTable('platform_asset_policies')) {
            return [];
        }
        $out = [];
        foreach (PlatformAssetPolicy::query()->get() as $row) {
            if (AssetRef::isValid($row->asset_kind . ':' . $row->asset_key)) {
                $out[$row->ref()] = $row->rule()->toArray();
            }
        }

        return $out;
    }

    public function grant(Site $site, string $ref, string $source = SiteAssetGrant::SOURCE_ADMIN_GRANT, ?string $reference = null, ?int $actorId = null): SiteAssetGrant
    {
        [$kind, $key] = $this->governedOrFail($ref);
        if (!in_array($source, SiteAssetGrant::SOURCES, true)) {
            throw new \DomainException('منبع اختصاص نامعتبر است.');
        }
        $rule = AssetAccessCatalog::rule($kind, $key, SiteAssetEntitlements::make(null, [], [], $this->overrides()));
        if ($rule !== null && $rule->isPublic) {
            throw new \DomainException('این مورد عمومی است و به اختصاص نیاز ندارد.');
        }

        $grant = DB::transaction(function () use ($site, $kind, $key, $source, $reference, $actorId) {
            $grant = SiteAssetGrant::query()->firstOrNew(['site_id' => $site->id, 'asset_kind' => $kind, 'asset_key' => $key]);
            $grant->fill([
                'status' => SiteAssetGrant::STATUS_ACTIVE,
                'source' => $source,
                'reference' => $reference !== null && trim($reference) !== '' ? mb_substr(trim($reference), 0, 191) : null,
                'granted_by' => $actorId,
                'granted_at' => now(),
                'revoked_by' => null,
                'revoked_at' => null,
            ])->save();

            return $grant;
        });

        $this->audit(self::EVENT_GRANTED, ['site_id' => $site->id, 'asset' => $ref, 'source' => $source, 'actor_id' => $actorId]);

        return $grant;
    }

    public function revoke(Site $site, string $ref, ?int $actorId = null): bool
    {
        [$kind, $key] = $this->governedOrFail($ref);
        $grant = SiteAssetGrant::query()->where(['site_id' => $site->id, 'asset_kind' => $kind, 'asset_key' => $key])->active()->first();
        if ($grant === null) {
            return false;
        }
        $grant->forceFill(['status' => SiteAssetGrant::STATUS_REVOKED, 'revoked_by' => $actorId, 'revoked_at' => now()])->save();
        $this->audit(self::EVENT_REVOKED, ['site_id' => $site->id, 'asset' => $ref, 'actor_id' => $actorId]);

        return true;
    }

    public function setVertical(Site $site, ?string $vertical, ?int $actorId = null): void
    {
        $vertical = $vertical === '' ? null : $vertical;
        if ($vertical !== null && !BusinessVerticals::isKnown($vertical)) {
            throw new \DomainException('حوزهٔ کسب‌وکار نامعتبر است.');
        }
        $previous = $site->business_vertical;
        if ($previous === $vertical) {
            return;
        }
        $site->forceFill(['business_vertical' => $vertical])->save();
        $this->audit(self::EVENT_VERTICAL_CHANGED, ['site_id' => $site->id, 'from' => $previous, 'to' => $vertical, 'actor_id' => $actorId]);
    }

    /**
     * @param array{is_public?: bool, required_vertical?: ?string, entitlement_keys?: list<string>, locked_display?: string} $rule
     */
    public function setOverride(string $ref, array $rule, ?string $note = null, ?int $actorId = null): PlatformAssetPolicy
    {
        [$kind, $key] = $this->governedOrFail($ref);
        $vertical = $rule['required_vertical'] ?? null;
        $vertical = $vertical === '' ? null : $vertical;
        if ($vertical !== null && !BusinessVerticals::isKnown($vertical)) {
            throw new \DomainException('حوزهٔ کسب‌وکار نامعتبر است.');
        }
        $isPublic = ($rule['is_public'] ?? false) === true;
        $keys = AssetAccessRule::cleanSlugs($rule['entitlement_keys'] ?? []);

        $row = PlatformAssetPolicy::query()->firstOrNew(['asset_kind' => $kind, 'asset_key' => $key]);
        $row->fill([
            'is_public' => $isPublic,
            'required_vertical' => $isPublic ? null : $vertical,
            'entitlement_keys' => $isPublic ? [] : $keys,
            'locked_display' => ($rule['locked_display'] ?? null) === AssetAccessRule::DISPLAY_TEASER ? AssetAccessRule::DISPLAY_TEASER : AssetAccessRule::DISPLAY_HIDDEN,
            'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            'updated_by' => $actorId,
        ])->save();

        $this->audit(self::EVENT_OVERRIDE_SET, ['asset' => $ref, 'rule' => $row->rule()->toArray(), 'actor_id' => $actorId]);

        return $row;
    }

    public function clearOverride(string $ref, ?int $actorId = null): bool
    {
        [$kind, $key] = $this->governedOrFail($ref);
        $deleted = PlatformAssetPolicy::query()->where(['asset_kind' => $kind, 'asset_key' => $key])->delete() > 0;
        if ($deleted) {
            $this->audit(self::EVENT_OVERRIDE_CLEARED, ['asset' => $ref, 'actor_id' => $actorId]);
        }

        return $deleted;
    }

    /** @return array{0: string, 1: string} */
    protected function governedOrFail(string $ref): array
    {
        $parts = AssetRef::parse($ref);
        if ($parts === null || !AssetAccessCatalog::isGoverned($parts[0], $parts[1])) {
            throw new \DomainException('این مورد در کاتالوگ دسترسی پلتفرم وجود ندارد.');
        }

        return $parts;
    }

    protected function audit(string $event, array $properties): void
    {
        try {
            $this->logger->log($event, null, null, $properties);
        } catch (\Throwable $e) {
            // The write already happened; a broken activity log must not
            // turn it into a 500, but it must not vanish silently either.
            \Illuminate\Support\Facades\Log::warning('asset_access.audit_failed', ['event' => $event, 'message' => $e->getMessage()] + $properties);
        }
    }
}
