<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;

class EducationFeature extends Model
{
    protected $table = 'edu_features';

    protected $fillable = [
        'site_id', 'module_slug', 'feature_slug', 'feature_name',
        'route_name', 'permissions_json', 'metadata_json', 'discovered_at',
    ];

    protected $casts = [
        'permissions_json' => 'array',
        'metadata_json'    => 'array',
        'discovered_at'    => 'datetime',
    ];

    public function scopeForSite($query, ?int $siteId)
    {
        if ($siteId === null) {
            return $query->whereNull('site_id');
        }
        return $query->where(function ($q) use ($siteId) {
            $q->whereNull('site_id')->orWhere('site_id', $siteId);
        });
    }

    public function scopeForModule($query, string $moduleSlug)
    {
        return $query->where('module_slug', $moduleSlug);
    }

    public function getMenuLocationAttribute(): ?string
    {
        return $this->metadata_json['menu_location'] ?? null;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->metadata_json['description'] ?? null;
    }
}
