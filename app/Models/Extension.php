<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Phase 3G-B-1 — master commercial identity row for an Extension product
 * (ADR-045). Global/catalog scope only: no site_id, no ownership-type
 * column — a customer-specific Extension is out of scope for this table by
 * construction (ADR-045 point 19).
 *
 * `vendor_slug`/`package_slug` together form the authoritative product
 * identity "{vendor-slug}/{package-slug}" (App\Services\ThemeBuilder\
 * Extension\ExtensionProductIdentity). `current_release_id` is deliberately
 * excluded from $fillable — mirroring Theme::$fillable's own exclusion of
 * current_release_id — so it can only ever be moved by
 * ExtensionReleaseService, never by generic mass-assignment.
 *
 * Only ever created/released through ExtensionReleaseService — the sole
 * writer, mirroring ThemeReleaseService's role for Theme.
 */
class Extension extends Model
{
    protected $fillable = [
        'vendor_slug',
        'package_slug',
        'name',
        'description',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $extension) {
            if ($extension->isDirty(['vendor_slug', 'package_slug']) && $extension->releases()->exists()) {
                throw new RuntimeException(
                    'Extension identity (vendor_slug/package_slug) is immutable once a release exists — no rename/alias support (ADR-045 point 7).'
                );
            }

            if ($extension->isDirty('current_release_id') && $extension->current_release_id !== null) {
                $release = ExtensionRelease::find($extension->current_release_id);
                if ($release && (int) $release->extension_id !== (int) $extension->id) {
                    throw new RuntimeException(
                        'current_release_id must reference an ExtensionRelease belonging to this Extension — cross-Extension current-release assignment is rejected.'
                    );
                }
            }
        });
    }

    public function releases()
    {
        return $this->hasMany(ExtensionRelease::class);
    }

    public function currentRelease()
    {
        return $this->belongsTo(ExtensionRelease::class, 'current_release_id');
    }

    public function identifier(): string
    {
        return $this->vendor_slug . '/' . $this->package_slug;
    }
}
