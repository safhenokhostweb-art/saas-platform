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
        'customer_mobile',
        'customer_name',
        'category',
        'type',
        'fr_status',
        'fr_status_updated_at',
        'fr_impact_score',
        'fr_estimated_quarter',
        'tags',
        'attachments',
        'watchers',
        'closed_at',
        'first_response_at',
        'resolved_at',
    ];

    protected $casts = [
        'closed_at'            => 'datetime',
        'first_response_at'    => 'datetime',
        'resolved_at'          => 'datetime',
        'fr_status_updated_at' => 'datetime',
        'attachments'          => 'array',
        'tags'                 => 'array',
        'watchers'             => 'array',
    ];

    // ── Ticket type ──────────────────────────────────────────────────────────
    public const TYPE_SUPPORT         = 'support';
    public const TYPE_FEATURE_REQUEST = 'feature_request';

    // ── Support ticket statuses ──────────────────────────────────────────────
    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED      = 'closed';

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH   = 'high';

    // ── Feature Request 10-state workflow (Section K) ────────────────────────
    public const FR_SUBMITTED            = 'submitted';
    public const FR_RECEIVED             = 'received';
    public const FR_UNDER_REVIEW         = 'under_review';
    public const FR_FEASIBILITY_ANALYSIS = 'feasibility_analysis';
    public const FR_APPROVED             = 'approved';
    public const FR_REJECTED             = 'rejected';
    public const FR_SCHEDULED            = 'scheduled';
    public const FR_IN_DEVELOPMENT       = 'in_development';
    public const FR_TESTING              = 'testing';
    public const FR_RELEASED             = 'released';

    public static function frStatuses(): array
    {
        return [
            self::FR_SUBMITTED            => 'ارسال شده',
            self::FR_RECEIVED             => 'دریافت شده',
            self::FR_UNDER_REVIEW         => 'در حال بررسی',
            self::FR_FEASIBILITY_ANALYSIS => 'امکان‌سنجی',
            self::FR_APPROVED             => 'تأیید شده',
            self::FR_REJECTED             => 'رد شده',
            self::FR_SCHEDULED            => 'برنامه‌ریزی شده',
            self::FR_IN_DEVELOPMENT       => 'در حال توسعه',
            self::FR_TESTING              => 'تست',
            self::FR_RELEASED             => 'منتشر شده',
        ];
    }

    public function frStatusLabel(): string
    {
        return static::frStatuses()[$this->fr_status ?? self::FR_SUBMITTED] ?? 'نامشخص';
    }

    public function isFeatureRequest(): bool
    {
        return $this->type === self::TYPE_FEATURE_REQUEST;
    }

    public function frStatusIndex(): int
    {
        $keys = array_keys(static::frStatuses());
        return array_search($this->fr_status ?? self::FR_SUBMITTED, $keys) ?: 0;
    }

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

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class, 'ticket_id')->orderBy('created_at');
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'ticket_id')
            ->where('is_internal', true)
            ->orderBy('created_at');
    }

    public function publicReplies(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'ticket_id')
            ->where('is_internal', false)
            ->orderBy('created_at');
    }

    // ── Feature Request relationships ────────────────────────────────────────

    public function votes(): HasMany
    {
        return $this->hasMany(\SaaSPlatform\Modules\Tickets\Models\TicketVote::class, 'ticket_id');
    }

    public function frSubscribers(): HasMany
    {
        return $this->hasMany(\SaaSPlatform\Modules\Tickets\Models\TicketFrSubscriber::class, 'ticket_id');
    }
}
