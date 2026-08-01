<?php

namespace SaaSPlatform\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaaSPlatform\Modules\Crm\Models\Concerns\HasSiteTenantScope;

class CrmTask extends Model
{
    use HasSiteTenantScope;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'crm_tasks';

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $fillable = [
        'site_id',
        'subject_type',
        'subject_id',
        'title',
        'description',
        'due_at',
        'remind_at',
        'status',
        'assigned_to',
        'created_by',
        'completed_at',
        'reminder_sent_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'remind_at' => 'datetime',
        'completed_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function complete(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED, 'completed_at' => now()]);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeDueSoon(Builder $query, int $days = 7): Builder
    {
        return $query->pending()->whereNotNull('due_at')->where('due_at', '<=', now()->addDays($days));
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()->whereNotNull('due_at')->where('due_at', '<', now());
    }

    public function scopeNotYetReminded(Builder $query): Builder
    {
        return $query->whereNull('reminder_sent_at');
    }
}
