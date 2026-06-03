<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationWalkthrough extends Model
{
    protected $table = 'edu_walkthroughs';

    protected $fillable = [
        'title', 'slug', 'description', 'site_id',
        'trigger_route', 'trigger_selector', 'trigger_condition',
        'target_roles', 'module_slug', 'is_active', 'sort_order', 'meta',
    ];

    protected $casts = [
        'target_roles' => 'array',
        'meta'         => 'array',
        'is_active'    => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(EducationWalkthroughStep::class, 'edu_walkthrough_id')
            ->orderBy('step_number');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(EducationWalkthroughProgress::class, 'edu_walkthrough_id');
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

    public function scopeForRoute($query, string $routeName)
    {
        return $query->where('trigger_route', $routeName);
    }
}
