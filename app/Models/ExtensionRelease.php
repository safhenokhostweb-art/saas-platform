<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Phase 3G-B-1 — append-only commercial release ledger row for an Extension
 * Product (ADR-045). Mirrors ThemeRelease's immutability contract exactly:
 * every column except `status` is frozen after creation (enforced below,
 * not just by convention); rows are never deleted.
 *
 * Boundary of that guarantee, identical to ThemeRelease's own documented
 * one: the guard below is an Eloquent model-event listener, so it protects
 * every normal save()/update() call (including mass-assignment) but does
 * not intercept saveQuietly()/updateQuietly() or a bulk query-builder write.
 * Nothing in this codebase does either against extension_releases; this
 * must stay true.
 *
 * `manifest` is persisted as JSON with deliberately minimal, honest
 * structural validation only (ExtensionReleaseService::assertValidManifest())
 * — the canonical ExtensionManifest DTO/schema is deferred to 3G-B-2
 * (ADR-045 point 10). Treat this column as TRANSITIONAL until then.
 */
class ExtensionRelease extends Model
{
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_DEPRECATED = 'deprecated';

    protected $fillable = [
        'extension_id',
        'version',
        'sdk_contract_version',
        'manifest',
        'status',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'sdk_contract_version' => 'integer',
        'manifest' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $release) {
            $dirty = array_keys($release->getDirty());
            $mutableFields = ['status', 'updated_at'];
            if (array_diff($dirty, $mutableFields)) {
                throw new RuntimeException(
                    'ExtensionRelease is an immutable release ledger row; only its status may transition. '
                    . 'Attempted to change: ' . implode(', ', array_diff($dirty, $mutableFields))
                );
            }
        });

        static::deleting(function () {
            throw new RuntimeException('ExtensionRelease rows can never be deleted — the release ledger is permanent.');
        });
    }

    public function extension()
    {
        return $this->belongsTo(Extension::class);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isCurrent(): bool
    {
        return $this->extension?->current_release_id === $this->id;
    }
}
