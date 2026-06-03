<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;

class EducationFeatureUsage extends Model
{
    protected $table = 'edu_feature_usage';

    protected $fillable = [
        'user_id', 'site_id', 'module_slug', 'feature_slug',
        'usage_count', 'first_used_at', 'last_used_at',
    ];

    protected $casts = [
        'usage_count'  => 'integer',
        'first_used_at'=> 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function scopeNeverUsed($query)
    {
        return $query->where('usage_count', 0);
    }

    public function scopeUsedBelow($query, int $threshold)
    {
        return $query->where('usage_count', '<', $threshold)->where('usage_count', '>', 0);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSite($query, ?int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeForModule($query, string $moduleSlug)
    {
        return $query->where('module_slug', $moduleSlug);
    }
}
