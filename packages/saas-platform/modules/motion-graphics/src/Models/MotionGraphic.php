<?php

namespace SaaSPlatform\Modules\MotionGraphics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class MotionGraphic extends Model
{
    use SoftDeletes;

    public const OWNERSHIP_GLOBAL = 'global';
    public const OWNERSHIP_CUSTOMER_SPECIFIC = 'customer_specific';

    /**
     * Commercial tier (ADR-078). Stored as `runtime_configuration.license_tier`.
     * Anything missing or unrecognised is treated as premium, so an asset
     * is never handed out for free by accident.
     */
    public const LICENSE_TIER_FREE = 'free';
    public const LICENSE_TIER_PREMIUM = 'premium';

    protected $fillable = [
        'name', 'slug', 'description', 'ownership_type', 'owner_site_identifier',
        'group_id', 'category_id', 'engine', 'asset_type', 'source_definition_json',
        'editable_schema_json', 'ai_metadata_json', 'runtime_configuration', 'dimensions',
        'accessibility', 'latest_working_version', 'approved_version', 'published_version',
        'status', 'created_by_actor_identifier', 'published_at', 'archived_at',
    ];

    protected $hidden = ['source_definition_json', 'editable_schema_json', 'ai_metadata_json'];

    protected $casts = [
        'source_definition_json' => 'encrypted:array',
        'editable_schema_json' => 'encrypted:array',
        'ai_metadata_json' => 'encrypted:array',
        'runtime_configuration' => 'array',
        'dimensions' => 'array',
        'accessibility' => 'array',
        'latest_working_version' => 'integer',
        'approved_version' => 'integer',
        'published_version' => 'integer',
        'published_at' => 'immutable_datetime',
        'archived_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $motion): void {
            $motion->uuid ??= (string) Str::uuid();
            $motion->assertOwnership();
        });
        static::updating(fn (self $motion) => $motion->assertOwnership());
    }

    public function assertOwnership(): void
    {
        if (! in_array($this->ownership_type, [self::OWNERSHIP_GLOBAL, self::OWNERSHIP_CUSTOMER_SPECIFIC], true)) {
            throw new InvalidArgumentException('Unsupported motion ownership type.');
        }
        if ($this->ownership_type === self::OWNERSHIP_GLOBAL && $this->owner_site_identifier !== null) {
            throw new InvalidArgumentException('Global motions cannot have a customer site owner.');
        }
        if ($this->ownership_type === self::OWNERSHIP_CUSTOMER_SPECIFIC && blank($this->owner_site_identifier)) {
            throw new InvalidArgumentException('Customer-specific motions require one site identifier.');
        }
    }

    public function licenseTier(): string
    {
        $tier = ($this->runtime_configuration ?? [])['license_tier'] ?? null;

        return $tier === self::LICENSE_TIER_FREE ? self::LICENSE_TIER_FREE : self::LICENSE_TIER_PREMIUM;
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MotionGroup::class, 'group_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MotionCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MotionTag::class, 'motion_graphic_tag');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MotionGraphicVersion::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(MotionGraphicDeployment::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', '%'.trim($term).'%')
                ->orWhere('slug', 'like', '%'.trim($term).'%')
                ->orWhere('description', 'like', '%'.trim($term).'%')
                ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', '%'.trim($term).'%'));
        });
    }
}
