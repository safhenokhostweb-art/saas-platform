<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteCaptchaSetting extends Model
{
    protected $fillable = [
        'site_id',
        'captcha_enabled',
        'captcha_type',
        'hcaptcha_site_key',
        'hcaptcha_secret_key',
    ];

    protected $casts = [
        'captcha_enabled' => 'boolean',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
