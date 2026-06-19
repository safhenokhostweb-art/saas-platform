<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ThemePack extends Model
{
    protected $fillable = [
        'uuid', 'name', 'description', 'version', 'author', 'license_type',
        'tags', 'preview_image_url', 'includes', 'pack_data',
        'is_public', 'is_active', 'site_id', 'created_by',
    ];

    protected $casts = [
        'tags'       => 'array',
        'includes'   => 'array',
        'pack_data'  => 'array',
        'is_public'  => 'boolean',
        'is_active'  => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function scopeActive($query)  { return $query->where('is_active', true); }
    public function scopePlatform($query){ return $query->whereNull('site_id'); }

    public function toExportArray(): array
    {
        return [
            'uuid'              => $this->uuid,
            'name'              => $this->name,
            'description'       => $this->description,
            'version'           => $this->version,
            'author'            => $this->author,
            'license_type'      => $this->license_type,
            'tags'              => $this->tags ?? [],
            'includes'          => $this->includes ?? [],
            'pack_data'         => $this->pack_data ?? [],
            'preview_image_url' => $this->preview_image_url,
            'exported_at'       => now()->toISOString(),
            'exporter'          => 'saas-platform-tb3',
            'format'            => 'theme-pack-v1',
        ];
    }

    /** Summarize what this pack contains for display. */
    public function contentSummary(): array
    {
        $data = $this->pack_data ?? [];
        return [
            'global_styles' => !empty($data['global_styles']),
            'headers'       => count($data['headers'] ?? []),
            'footers'       => count($data['footers'] ?? []),
            'pages'         => count($data['pages'] ?? []),
            'templates'     => count($data['templates'] ?? []),
            'demo_data'     => !empty($data['demo_data']),
        ];
    }
}
