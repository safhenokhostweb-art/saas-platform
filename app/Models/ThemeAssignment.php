<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeAssignment extends Model
{
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_SUPERSEDED = 'superseded';

    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_MARKETPLACE = 'marketplace';
    public const SOURCE_BUNDLE = 'bundle';

    protected $fillable = [
        'site_id',
        'theme_id',
        'theme_release_id',
        'platform_license_id',
        'theme_license_id',
        'domain',
        'source',
        'status',
        'assigned_by',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }

    public function release()
    {
        return $this->belongsTo(ThemeRelease::class, 'theme_release_id');
    }

    public function platformLicense()
    {
        return $this->belongsTo(PlatformLicense::class);
    }

    public function themeLicense()
    {
        return $this->belongsTo(ThemeLicense::class);
    }
}
