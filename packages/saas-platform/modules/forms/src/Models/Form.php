<?php

namespace SaaSPlatform\Modules\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Form extends Model
{
    use SoftDeletes;

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
        'settings',
        'template_id',
        'step_count',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'enable_simple_captcha' => 'boolean',
        'enable_hcaptcha'      => 'boolean',
        'settings'             => 'array',
        'step_count'           => 'integer',
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
        $siteModel = config('forms.site_model');
        if (!$siteModel || !class_exists($siteModel)) {
            // form_site pivot table does not exist in single-tenant setups; avoid any JOIN on it.
            return $this->hasMany(static::class, 'id', 'id')->whereRaw('1 = 0');
        }
        return $this->belongsToMany($siteModel, 'form_site')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function automations()
    {
        return $this->hasMany(FormAutomation::class);
    }

    public function isMultiStep(): bool
    {
        return ($this->settings['is_multi_step'] ?? false) === true;
    }

    public function getStepsConfig(): array
    {
        return $this->settings['steps'] ?? [];
    }

    public function getSuccessMessage(): string
    {
        return $this->settings['success_message'] ?? 'فرم با موفقیت ارسال شد.';
    }

    public function getSubmitButtonText(): string
    {
        return $this->settings['submit_button_text'] ?? 'ارسال فرم';
    }
}
