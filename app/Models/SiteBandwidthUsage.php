<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteBandwidthUsage extends Model
{
    protected $table = 'site_bandwidth_usage';

    protected $fillable = [
        'site_id',
        'year',
        'month',
        'bytes',
        'request_count',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function formattedBytes(): string
    {
        return SiteResourceUsage::formatBytes((int) $this->bytes);
    }

    public static function recordRequests(int $siteId, int $bytes, int $requestCount = 1): void
    {
        self::updateOrCreate(
            [
                'site_id' => $siteId,
                'year'    => (int) now()->format('Y'),
                'month'   => (int) now()->format('n'),
            ],
            []
        );

        self::where('site_id', $siteId)
            ->where('year', (int) now()->format('Y'))
            ->where('month', (int) now()->format('n'))
            ->increment('bytes', $bytes);

        self::where('site_id', $siteId)
            ->where('year', (int) now()->format('Y'))
            ->where('month', (int) now()->format('n'))
            ->increment('request_count', $requestCount);
    }

    public static function currentMonthForSite(int $siteId): ?self
    {
        return self::where('site_id', $siteId)
            ->where('year', (int) now()->format('Y'))
            ->where('month', (int) now()->format('n'))
            ->first();
    }

    public function scopeForCurrentMonth($query)
    {
        return $query->where('year', (int) now()->format('Y'))
                     ->where('month', (int) now()->format('n'));
    }
}
