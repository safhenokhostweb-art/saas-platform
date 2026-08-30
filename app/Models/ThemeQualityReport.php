<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One Commercial Quality Gate evaluation (Phase 2a). See
 * App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate.
 */
class ThemeQualityReport extends Model
{
    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'theme_id',
        'theme_release_id',
        'version',
        'status',
        'checks',
        'evaluated_at',
    ];

    protected $casts = [
        'checks' => 'array',
        'evaluated_at' => 'datetime',
    ];

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }

    public function release()
    {
        return $this->belongsTo(ThemeRelease::class, 'theme_release_id');
    }

    public function isPassing(): bool
    {
        return $this->status === self::STATUS_PASSED;
    }

    /** @return array<int, array> check-result rows whose status is failed AND severity is blocking */
    public function blockingFailures(): array
    {
        return array_values(array_filter(
            $this->checks ?? [],
            fn (array $c) => ($c['status'] ?? null) === 'failed' && ($c['severity'] ?? null) === 'blocking'
        ));
    }

    /** @return array<int, array> non-blocking findings (warnings + unverified), kept visible rather than discarded */
    public function nonBlockingFindings(): array
    {
        return array_values(array_filter(
            $this->checks ?? [],
            fn (array $c) => !(($c['status'] ?? null) === 'failed' && ($c['severity'] ?? null) === 'blocking')
                && ($c['status'] ?? null) !== 'passed'
        ));
    }
}
