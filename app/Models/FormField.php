<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
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
