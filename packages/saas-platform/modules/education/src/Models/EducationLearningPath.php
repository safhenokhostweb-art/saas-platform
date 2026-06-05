<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EducationLearningPath extends Model
{
    protected $table = 'edu_learning_paths';

    public const TYPE_MANUAL   = 'manual';
    public const TYPE_ROLE     = 'role';
    public const TYPE_USAGE    = 'usage';

    protected $fillable = [
        'title', 'slug', 'description', 'site_id',
        'target_roles', 'module_slug', 'feature_key', 'auto_generated',
        'prerequisite_path_ids', 'path_type',
        'is_active', 'sort_order',
        'estimated_minutes', 'completion_reward', 'meta',
        'documentation_version', 'documentation_hash',
        'last_generated_at', 'last_synced_at', 'needs_regeneration',
    ];

    protected $casts = [
        'target_roles'          => 'array',
        'prerequisite_path_ids' => 'array',
        'completion_reward'     => 'array',
        'meta'                  => 'array',
        'is_active'             => 'boolean',
        'auto_generated'        => 'boolean',
        'needs_regeneration'    => 'boolean',
        'sort_order'            => 'integer',
        'estimated_minutes'     => 'integer',
        'documentation_version' => 'integer',
        'last_generated_at'     => 'datetime',
        'last_synced_at'        => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $path) {
            if (empty($path->slug)) {
                $path->slug = Str::slug($path->title) . '-' . Str::random(4);
            }
        });
    }

    public function steps(): HasMany
    {
        return $this->hasMany(EducationLearningPathStep::class, 'edu_learning_path_id')
            ->orderBy('step_number');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSite($query, ?int $siteId)
    {
        return $query->where(function ($q) use ($siteId) {
            $q->whereNull('site_id')->orWhere('site_id', $siteId);
        });
    }

    public function scopeForRole($query, ?string $role)
    {
        if (! $role) {
            return $query;
        }
        return $query->where(function ($q) use ($role) {
            $q->whereNull('target_roles')
              ->orWhereJsonContains('target_roles', $role);
        });
    }
}
