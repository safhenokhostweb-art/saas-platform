<?php

namespace SaaSPlatform\Modules\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaaSPlatform\CoreUtils\Traits\HasUuid;
use SaaSPlatform\CoreUtils\Traits\HasUserStamps;

class FormSubmission extends Model
{
    use SoftDeletes, HasUuid, HasUserStamps;

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
        return $this->belongsTo(\App\Models\Site::class);
    }
}
