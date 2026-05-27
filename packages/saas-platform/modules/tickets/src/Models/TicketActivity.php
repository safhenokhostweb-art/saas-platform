<?php

namespace SaaSPlatform\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'actor_id',
        'action_type',
        'old_value',
        'new_value',
        'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Action type constants ────────────────────────────────────────────────

    public const ACTION_CREATED          = 'created';
    public const ACTION_STATUS_CHANGED   = 'status_changed';
    public const ACTION_ASSIGNED         = 'assigned';
    public const ACTION_UNASSIGNED       = 'unassigned';
    public const ACTION_REPLIED          = 'replied';
    public const ACTION_NOTE_ADDED       = 'note_added';
    public const ACTION_PRIORITY_CHANGED = 'priority_changed';
    public const ACTION_WATCHER_ADDED    = 'watcher_added';
    public const ACTION_WATCHER_REMOVED  = 'watcher_removed';
    public const ACTION_SLA_BREACHED     = 'sla_breached';
    public const ACTION_CLOSED           = 'closed';
    public const ACTION_REOPENED         = 'reopened';

    // ─── Relationships ────────────────────────────────────────────────────────

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(config('tickets.user_model'), 'actor_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function actorName(): string
    {
        return $this->metadata['actor_name']
            ?? $this->actor?->name
            ?? 'سیستم';
    }

    public function label(): string
    {
        return match ($this->action_type) {
            self::ACTION_CREATED          => 'تیکت ایجاد شد',
            self::ACTION_STATUS_CHANGED   => 'وضعیت تغییر کرد',
            self::ACTION_ASSIGNED         => 'تخصیص داده شد',
            self::ACTION_UNASSIGNED       => 'تخصیص لغو شد',
            self::ACTION_REPLIED          => 'پاسخ ثبت شد',
            self::ACTION_NOTE_ADDED       => 'یادداشت داخلی اضافه شد',
            self::ACTION_PRIORITY_CHANGED => 'اولویت تغییر کرد',
            self::ACTION_WATCHER_ADDED    => 'ناظر اضافه شد',
            self::ACTION_WATCHER_REMOVED  => 'ناظر حذف شد',
            self::ACTION_SLA_BREACHED     => 'SLA نقض شد',
            self::ACTION_CLOSED           => 'تیکت بسته شد',
            self::ACTION_REOPENED         => 'تیکت بازگشایی شد',
            default                       => $this->action_type,
        };
    }

    public function iconColor(): string
    {
        return match ($this->action_type) {
            self::ACTION_CREATED          => 'indigo',
            self::ACTION_STATUS_CHANGED   => 'blue',
            self::ACTION_ASSIGNED         => 'purple',
            self::ACTION_UNASSIGNED       => 'gray',
            self::ACTION_REPLIED          => 'green',
            self::ACTION_NOTE_ADDED       => 'amber',
            self::ACTION_PRIORITY_CHANGED => 'orange',
            self::ACTION_WATCHER_ADDED    => 'teal',
            self::ACTION_WATCHER_REMOVED  => 'slate',
            self::ACTION_SLA_BREACHED     => 'red',
            self::ACTION_CLOSED           => 'emerald',
            self::ACTION_REOPENED         => 'yellow',
            default                       => 'gray',
        };
    }
}
