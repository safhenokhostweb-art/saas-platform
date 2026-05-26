<?php

namespace SaaSPlatform\Modules\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaaSPlatform\CoreUtils\Traits\HasUuid;
use SaaSPlatform\CoreUtils\Traits\HasUserStamps;

class Form extends Model
{
    use SoftDeletes, HasUuid, HasUserStamps;

    protected $table = 'forms';

    protected $fillable = [
        'title',
        'slug',
        'is_active',
        'enable_simple_captcha',
        'enable_hcaptcha',
        'meta_title',
        'meta_description',
        'canonical_url',
        'meta_robots',
        'intro_text',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'enable_simple_captcha' => 'boolean',
        'enable_hcaptcha' => 'boolean',
    ];

    public function fields()
    {
        return $this->hasMany(FormField::class)->orderBy('position');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function sites()
    {
        return $this->belongsToMany(config('forms.site_model'), 'form_site')
            ->withPivot('is_active')
            ->withTimestamps();
    }
}
