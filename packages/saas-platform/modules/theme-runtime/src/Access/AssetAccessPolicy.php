<?php

namespace SaaSPlatform\ThemeRuntime\Access;

/**
 * ADR-087: the one decision function. Pure: both hosts call it with the
 * same rule (manifest, or the platform's override) and the same
 * entitlements (built by the platform, or rebuilt from its payload), so
 * the platform panel, the platform canvas and the tenant editor can never
 * disagree.
 *
 * Order: public → platform designer → explicit grant → declared
 * conditions. A vertical mismatch hides the asset (another industry's kit
 * is noise, not an upgrade path); a missing entitlement key or a missing
 * grant locks it, listed or hidden per the rule's `locked_display`.
 */
final class AssetAccessPolicy
{
    public static function decide(AssetAccessRule $rule, string $ref, SiteAssetEntitlements $site): AssetAccessDecision
    {
        $label = $rule->familyLabel;

        if ($rule->isPublic) {
            return new AssetAccessDecision(AssetAccessDecision::STATE_AVAILABLE, AssetAccessDecision::REASON_PUBLIC, false);
        }
        if ($site->platformDesigner) {
            return new AssetAccessDecision(AssetAccessDecision::STATE_AVAILABLE, AssetAccessDecision::REASON_PLATFORM_DESIGNER, true, $label);
        }
        if ($site->hasGrant($ref) || ($ref === 'widget:header_glass_nimbus' && ($site->hasGrant('header:header-glass-nimbus') || $site->hasGrant('widget:header_glass_nimbus')))) {
            return new AssetAccessDecision(AssetAccessDecision::STATE_AVAILABLE, AssetAccessDecision::REASON_GRANT, true, $label);
        }

        $lockedState = $rule->lockedDisplay === AssetAccessRule::DISPLAY_TEASER
            ? AssetAccessDecision::STATE_LOCKED
            : AssetAccessDecision::STATE_HIDDEN;

        if (!$rule->hasConditions()) {
            return new AssetAccessDecision($lockedState, AssetAccessDecision::REASON_GRANT_REQUIRED, true, $label);
        }
        if ($rule->requiredVertical !== null && $site->vertical !== $rule->requiredVertical) {
            return new AssetAccessDecision(AssetAccessDecision::STATE_HIDDEN, AssetAccessDecision::REASON_VERTICAL_MISMATCH, true, $label);
        }
        if ($rule->entitlementKeys !== [] && !$site->holdsAny($rule->entitlementKeys)) {
            return new AssetAccessDecision($lockedState, AssetAccessDecision::REASON_ENTITLEMENT_REQUIRED, true, $label);
        }

        return new AssetAccessDecision(AssetAccessDecision::STATE_AVAILABLE, AssetAccessDecision::REASON_ENTITLED, true, $label);
    }
}
