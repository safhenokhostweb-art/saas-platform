<?php

namespace SaaSPlatform\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use SaaSPlatform\Modules\Crm\Models\Concerns\HasSiteTenantScope;

/**
 * Phase 19 — Part 6: append-only timeline entry (call/meeting/email/sms/
 * note/system), polymorphic against Contact/Company/Lead/Opportunity.
 * No `updated_at` — mirrors TicketActivity's precedent.
 */
class CrmActivity extends Model
{
    use HasSiteTenantScope;

    public const UPDATED_AT = null;

    public const TYPE_CALL = 'call';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_EMAIL = 'email';
    public const TYPE_SMS = 'sms';
    public const TYPE_NOTE = 'note';
    public const TYPE_SYSTEM = 'system';

    protected $table = 'crm_activities';

    protected $fillable = [
        'site_id',
        'subject_type',
        'subject_id',
        'type',
        'subject',
        'body',
        'occurred_at',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(CrmAttachment::class, 'attachable');
    }
}
