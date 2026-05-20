<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoMeta extends Model
{
    protected $fillable = [
        'title',
        'description',
        'canonical',
        'og',
        'twitter',
        'schema',
        'noindex',
    ];

    protected $casts = [
        'og' => 'array',
        'twitter' => 'array',
        'schema' => 'array',
        'noindex' => 'boolean',
    ];

    public function seoable()
    {
        return $this->morphTo();
    }
}
