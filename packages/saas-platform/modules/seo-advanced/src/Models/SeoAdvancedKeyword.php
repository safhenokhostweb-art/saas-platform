<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Models;

use Illuminate\Database\Eloquent\Model;

class SeoAdvancedKeyword extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'seo_advanced_keywords';

    /**
     * Mirrors the migration's DB-level default — without this, a freshly
     * created in-memory model would report status=null until reloaded
     * from the database (Eloquent's create() never hydrates DB-side
     * column defaults back into the instance it returns).
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected $fillable = [
        'site_id',
        'keyword',
        'target_url',
        'status',
        'position',
        'position_history',
    ];

    protected $casts = [
        'position' => 'integer',
        'position_history' => 'array',
    ];

    /**
     * Phase 18-D — Part 4: manual, admin-entered position tracking — this
     * module never calls any external rank-tracking service. Each entry
     * is a simple {date, position} pair, oldest first.
     */
    public function recordPosition(int $position, ?string $date = null): void
    {
        $history = $this->position_history ?? [];
        $history[] = ['date' => $date ?? now()->toDateString(), 'position' => $position];

        $this->update([
            'position' => $position,
            'position_history' => $history,
        ]);
    }
}
