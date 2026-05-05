<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    protected $fillable = ['title', 'slug', 'is_active'];

    public function fields()
    {
        return $this->hasMany(FormField::class)->orderBy('position');
    }
    public function sites()
    {
        return $this->belongsToMany(\App\Models\Site::class, 'form_site')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function submissions()
    {
        return $this->hasMany(\App\Models\FormSubmission::class);
    }
}
