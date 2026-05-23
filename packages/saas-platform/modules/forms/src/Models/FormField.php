<?php

namespace SaaSPlatform\Modules\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaaSPlatform\CoreUtils\Traits\HasUuid;
use SaaSPlatform\CoreUtils\Traits\HasUserStamps;

class FormField extends Model
{
    use SoftDeletes, HasUuid, HasUserStamps;

    protected $table = 'form_fields';

    protected $fillable = [
        'form_id',
        'name',
        'label',
        'type',
        'options',
        'is_required',
        'position',
        'is_active',
        'placeholder',
        'help_text',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
