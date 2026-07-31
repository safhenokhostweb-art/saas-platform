<?php

namespace App\Modules\MotionGraphics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * A frozen definition of which Motion assets one platform template
 * requires, at specific resolved published versions. Immutable once
 * created — a change to a template's asset requirements creates a new
 * row with an incremented `package_version` rather than editing this
 * one, mirroring the standalone module's own MotionGraphicVersion
 * immutability pattern. Never stores source files, AI metadata, or the
 * module's internal ownership fields — only opaque {asset_id, version}
 * references. See docs/motion-persistence/PERSISTENCE_MODEL.md.
 */
final class TemplateAssetPackage extends Model
{
    protected $table = 'motion_template_asset_packages';

    protected $fillable = [
        'uuid', 'template_reference', 'required_assets', 'optional_assets',
        'compatibility_rules', 'package_version', 'created_by_actor_identifier',
    ];

    protected $casts = [
        'required_assets' => 'array',
        'optional_assets' => 'array',
        'compatibility_rules' => 'array',
        'package_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $package): void {
            $package->uuid ??= (string) Str::uuid();
        });
        static::updating(function (self $package): void {
            $immutable = [
                'template_reference', 'required_assets', 'optional_assets',
                'compatibility_rules', 'package_version', 'created_by_actor_identifier',
            ];
            if ($package->isDirty($immutable)) {
                throw new LogicException('Template Asset Package definitions are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Template Asset Package history cannot be deleted.'));
    }

    public function siteAssetPackages(): HasMany
    {
        return $this->hasMany(SiteAssetPackage::class);
    }
}
