<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SiteUpdate extends Model
{
    protected $fillable = [
        'site_id',
        'title',
        'version',
        'version_label',
        'shamsi_date',
        'release_date',
        'update_type',
        'features',
        'notes',
        'distributed_at',
        'is_published',
    ];

    protected $casts = [
        'features'       => 'array',
        'release_date'   => 'date',
        'distributed_at' => 'datetime',
        'is_published'   => 'boolean',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeFleet(Builder $query): Builder
    {
        return $query->whereNull('site_id');
    }

    public function scopeForSite(Builder $query, ?int $siteId): Builder
    {
        return $query->where(function (Builder $q) use ($siteId) {
            $q->whereNull('site_id');
            if ($siteId !== null) {
                $q->orWhere('site_id', $siteId);
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function isFleet(): bool
    {
        return is_null($this->site_id);
    }

    public function getTypeLabel(): string
    {
        return match ($this->update_type) {
            'feature'     => 'امکان جدید',
            'improvement' => 'بهبود و ارتقا',
            'security'    => 'امنیتی',
            'fix'         => 'رفع باگ',
            default       => $this->update_type ?: 'بروزرسانی',
        };
    }
}
