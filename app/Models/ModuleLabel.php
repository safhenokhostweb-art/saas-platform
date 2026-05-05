<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleLabel extends Model
{
    protected $fillable = [
        'site_id',
        'key',
        'custom_title',
        'custom_description',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function scopePlatform($query)
    {
        return $query->whereNull('site_id');
    }

    public function scopeForSite($query, $siteId)
    {
        if ($siteId === null) {
            return $query->whereNull('site_id');
        }
        return $query->where('site_id', $siteId);
    }
}
