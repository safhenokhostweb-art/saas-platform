<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleFeature extends Model
{
    protected $fillable = [
        'module_id',
        'key',
        'title',
        'is_active',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
