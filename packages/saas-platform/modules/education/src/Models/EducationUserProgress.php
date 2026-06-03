<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EducationUserProgress extends Model
{
    protected $table = 'edu_user_progress';

    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    protected $fillable = [
        'user_id', 'site_id',
        'progressable_id', 'progressable_type',
        'status', 'progress_percent',
        'started_at', 'completed_at', 'meta',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
        'meta'             => 'array',
    ];

    public function progressable(): MorphTo
    {
        return $this->morphTo();
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
