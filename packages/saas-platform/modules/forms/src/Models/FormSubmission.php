<?php

namespace SaaSPlatform\Modules\Forms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaaSPlatform\CoreUtils\Traits\HasUuid;
use SaaSPlatform\CoreUtils\Traits\HasUserStamps;
use SaaSPlatform\Modules\Forms\Models\Scopes\SiteTenantScope;

class FormSubmission extends Model
{
    use SoftDeletes, HasUuid, HasUserStamps;

    // PHASE 8.0 — Structural tenant isolation
    protected static function booted(): void
    {
        static::addGlobalScope(new SiteTenantScope());
    }

    /** Bypass tenant isolation — admin / platform contexts only. */
    public static function withoutTenantIsolation(): Builder
    {
        return static::withoutGlobalScope(SiteTenantScope::class);
    }

    protected $table = 'form_submissions';

    protected $fillable = [
        'form_id',
        'site_id',
        'data',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function site()
    {
        return $this->belongsTo(config('forms.site_model'));
    }
}
