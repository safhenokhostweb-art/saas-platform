<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;

class CanvasHeaderPreset extends Model
{
    protected $table = 'canvas_header_presets';

    protected $fillable = [
        'slug',
        'title',
        'description',
        'category_label',
        'preview_image',
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
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(CanvasAssetVersion::class, 'asset_slug', 'slug')
            ->where('asset_kind', AssetRef::KIND_HEADER)
            ->orderByDesc('id');
    }

    public function assetRef(): string
    {
        return AssetRef::make(AssetRef::KIND_HEADER, $this->slug);
    }

    public function isFree(): bool
    {
        return $this->access_type === 'free' || $this->price === 0;
    }

    public function isPaid(): bool
    {
        return !$this->isFree();
    }

    public function recordVersion(string $newVersion, ?string $changelog = null, ?array $summary = null, ?int $adminId = null): CanvasAssetVersion
    {
        $this->update(['current_version' => $newVersion]);

        return CanvasAssetVersion::create([
            'asset_kind' => AssetRef::KIND_HEADER,
            'asset_slug' => $this->slug,
            'version' => $newVersion,
            'changelog' => $changelog,
            'snapshot_summary' => $summary,
            'published_by_admin_id' => $adminId,
            'published_at' => now(),
        ]);
    }
}
