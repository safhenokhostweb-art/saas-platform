<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Append-only commercial release ledger row for a Theme Product.
 *
 * Distinct from ThemeRevision, which is an unrelated, pre-existing manual
 * draft/checkpoint tool (see ThemeBuilderService::saveRevision()). A
 * ThemeRelease is only ever written by ThemeReleaseService::release() and
 * represents a real, versioned, customer-facing product release — never a
 * mid-draft "just in case" snapshot.
 *
 * Immutability: once created, every column except `status` is permanently
 * frozen (enforced below, not just by convention). Rows are never deleted.
 *
 * Boundary of that guarantee: the guard below is an Eloquent model-event
 * listener, so it protects every normal save()/update() call (including
 * mass-assignment) but — like any event-based guard — does not intercept
 * saveQuietly()/updateQuietly() (which deliberately suppress model events)
 * or a bulk query-builder write (`ThemeRelease::where(...)->update(...)`,
 * which never instantiates/saves a model at all). Nothing in this codebase
 * does either against theme_releases; this must stay true — never add a
 * "quiet" or bulk write path against this table.
 */
class ThemeRelease extends Model
{
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_DEPRECATED = 'deprecated';

    protected $fillable = [
        'theme_id',
        'version',
        'changelog',
        'release_notes',
        'status',
        'compatibility',
        'dependencies',
        'snapshot',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'compatibility' => 'array',
        'dependencies' => 'array',
        'snapshot' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $release) {
            $dirty = array_keys($release->getDirty());
            $mutableFields = ['status', 'updated_at'];
            if (array_diff($dirty, $mutableFields)) {
                throw new RuntimeException(
                    'ThemeRelease is an immutable release ledger row; only its status may transition. '
                    . 'Attempted to change: ' . implode(', ', array_diff($dirty, $mutableFields))
                );
            }
        });

        static::deleting(function () {
            throw new RuntimeException('ThemeRelease rows can never be deleted — the release ledger is permanent.');
        });
    }

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }

    /**
     * Structured dependency rows (Phase 1e). Named `structuredDependencies`,
     * not `dependencies`, because `dependencies` is already the JSON-cast
     * attribute above — Eloquent cannot have a relation method and a cast
     * attribute share one name.
     */
    public function structuredDependencies()
    {
        return $this->hasMany(ThemeReleaseDependency::class);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Every Commercial Quality Gate evaluation ever linked to this release
     * (Phase 2c: only a passing evaluation is ever linked — see
     * ThemeCommercialQualityGate::publishIfCompliant()). Used by
     * Theme::isCommerciallyEligible() (Phase 2d) as the authoritative
     * evidence that this release was actually approved, not merely created.
     */
    public function qualityReports()
    {
        return $this->hasMany(ThemeQualityReport::class, 'theme_release_id');
    }

    public function isCurrent(): bool
    {
        return $this->theme?->current_release_id === $this->id;
    }
}
