<?php

namespace SaaSPlatform\Modules\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormField extends Model
{
    use SoftDeletes;

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
        'conditions',
        'section_id',
        'column_span',
        'default_value',
        'css_class',
        'validation_rules',
    ];

    protected $casts = [
        'options'          => 'array',
        'conditions'       => 'array',
        'validation_rules' => 'array',
        'is_required'      => 'boolean',
        'is_active'        => 'boolean',
        'column_span'      => 'integer',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function isFullWidth(): bool
    {
        return ($this->column_span ?? 12) === 12;
    }

    public function hasConditions(): bool
    {
        return ! empty($this->conditions);
    }

    public function isSectionHeader(): bool
    {
        return $this->type === 'section_header';
    }
}
