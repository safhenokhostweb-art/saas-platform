<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;

class CanvasSectionPreset extends Model
{
    protected $table = 'canvas_section_presets';

    protected $fillable = [
        'slug',
        'title',
        'description',
        'category_label',
        'preview_image',
        'blocks',
        'access_type',
        'price',
        'discount_price',
        'current_version',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'discount_price' => 'integer',
        'blocks' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(CanvasAssetVersion::class, 'asset_slug', 'slug')
            ->where('asset_kind', AssetRef::KIND_SECTION)
            ->orderByDesc('id');
    }

    public function assetRef(): string
    {
        return AssetRef::make(AssetRef::KIND_SECTION, $this->slug);
    }

    public function isFree(): bool
    {
        return $this->access_type === 'free';
    }

    public function effectivePrice(): int
    {
        return $this->discount_price !== null ? (int) $this->discount_price : (int) $this->price;
    }
}
