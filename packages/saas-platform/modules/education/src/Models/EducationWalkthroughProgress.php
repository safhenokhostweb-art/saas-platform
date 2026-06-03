<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EducationWalkthroughProgress extends Model
{
    protected $table = 'edu_walkthrough_progress';

    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_DISMISSED   = 'dismissed';

    protected $fillable = [
        'user_id', 'site_id', 'edu_walkthrough_id',
        'current_step', 'status', 'completed_at',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function walkthrough(): BelongsTo
    {
        return $this->belongsTo(EducationWalkthrough::class, 'edu_walkthrough_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'));
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
