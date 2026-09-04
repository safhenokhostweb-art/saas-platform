<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * One structured dependency of a ThemeRelease on an independently versioned
 * commercial product (a module, a motion asset/pack, an icon asset/pack, a
 * block pack, a design system, or a future product class) — per
 * THEME_PRODUCTIZATION_ARCHITECTURE.md Phase 2. Only ever written by
 * ThemeReleaseService (release()/attachDependencies()), the same sole
 * writer that owns theme_releases itself.
 *
 * Immutable once created, with the same documented boundary as ThemeRelease
 * (App\Models\ThemeRelease): the guard below is an Eloquent event listener,
 * so it does not intercept saveQuietly()/updateQuietly() or a bulk
 * query-builder write. Nothing in this codebase does either against this
 * table; that must stay true.
 */
class ThemeReleaseDependency extends Model
{
    // Known dependency types as of Phase 1e. This is NOT an exhaustive or
    // enforced list — dependency_type is an open enum at the validation
    // layer (ThemeReleaseService::assertValidDependencies()) precisely so a
    // future product class needs no schema or code change here. These
    // constants exist only as a documented, typo-safe convenience for
    // callers who already know their type.
    public const TYPE_MODULE = 'module';
    public const TYPE_MOTION_ASSET = 'motion_asset';
    public const TYPE_MOTION_PACK = 'motion_pack';
    public const TYPE_ICON_ASSET = 'icon_asset';
    public const TYPE_ICON_PACK = 'icon_pack';
    public const TYPE_BLOCK_PACK = 'block_pack';
    public const TYPE_DESIGN_SYSTEM = 'design_system';

    // Phase 3G-B-3 (ADR-045 point 13): identifier is the canonical Extension
    // PRODUCT identity "{vendor-slug}/{package-slug}" (never an ext:* block
    // type, never a numeric extensions.id) — see
    // App\Services\ThemeBuilder\Dependency\ThemeExtensionDependencyExtractor.
    public const TYPE_EXTENSION = 'extension';

    protected $fillable = [
        'theme_release_id',
        'dependency_type',
        'dependency_identifier',
        'version_constraint',
        'is_required',
        'metadata',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException(
                'ThemeReleaseDependency rows are immutable once attached to a release — none of their columns may ever change.'
            );
        });

        static::deleting(function () {
            throw new RuntimeException(
                'ThemeReleaseDependency rows can never be deleted — they are part of the immutable release ledger.'
            );
        });
    }

    public function release()
    {
        return $this->belongsTo(ThemeRelease::class, 'theme_release_id');
    }
}
