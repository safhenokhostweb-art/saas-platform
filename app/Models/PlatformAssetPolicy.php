<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessRule;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;

/**
 * ADR-087: the platform's override of one asset's declared access rule.
 * Written only by SiteAssetEntitlementService::setOverride()/clearOverride().
 */
class PlatformAssetPolicy extends Model
{
    protected $fillable = [
        'asset_kind', 'asset_key', 'is_public', 'required_vertical', 'entitlement_keys',
        'locked_display', 'note', 'updated_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'entitlement_keys' => 'array',
    ];

    public function ref(): string
    {
        return AssetRef::make($this->asset_kind, $this->asset_key);
    }

    public function rule(): AssetAccessRule
    {
        return AssetAccessRule::fromArray([
            'is_public' => (bool) $this->is_public,
            'required_vertical' => $this->required_vertical,
            'entitlement_keys' => $this->entitlement_keys ?? [],
            'locked_display' => $this->locked_display,
        ]);
    }
}
