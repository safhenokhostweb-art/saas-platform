<?php

namespace SaaSPlatform\Modules\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FormTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'form_templates';

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'category',
        'thumbnail',
        'schema',
        'is_public',
        'site_id',
    ];

    protected $casts = [
        'schema'    => 'array',
        'is_public' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getFieldCount(): int
    {
        return count($this->schema['fields'] ?? []);
    }
}
