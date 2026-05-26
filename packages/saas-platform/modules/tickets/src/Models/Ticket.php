<?php

namespace SaaSPlatform\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaaSPlatform\Modules\Tickets\Models\Scopes\SiteTenantScope;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_number',
        'subject',
        'description',
        'status',
        'priority',
        'customer_id',
        'assigned_to',
        'site_id',
        'customer_email',
        'customer_name',
        'category',
        'tags',
        'attachments',
        'closed_at',
        'first_response_at',
        'resolved_at',
    ];

    protected $casts = [
        'closed_at'         => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at'       => 'datetime',
        'attachments'       => 'array',
        'tags'              => 'array',
    ];

    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED      = 'closed';

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH   = 'high';

    // PHASE 8.0 — Structural tenant isolation
    protected static function booted(): void
    {
        static::addGlobalScope(new SiteTenantScope());
    }

    /**
     * Bypass tenant isolation for admin / platform contexts.
     * MUST only be called from admin controllers, migrations, or validated internal services.
     */
    public static function withoutTenantIsolation(): Builder
    {
        return static::withoutGlobalScope(SiteTenantScope::class);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(config('tickets.user_model'), 'customer_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(config('tickets.user_model'), 'assigned_to');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(config('tickets.site_model'), 'site_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'ticket_id')->orderBy('created_at');
    }
}
