<?php

namespace SaaSPlatform\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketReply extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'author_type',
        'is_internal',
        'customer_email',
        'customer_name',
        'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_internal' => 'boolean',
    ];

    public const AUTHOR_STAFF = 'staff';
    public const AUTHOR_CUSTOMER = 'customer';

    /**
     * Get the ticket that owns the reply.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * Get the user that wrote the reply.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('tickets.user_model'), 'user_id');
    }

    public function isFromStaff(): bool
    {
        return $this->author_type === self::AUTHOR_STAFF;
    }

    public function isInternal(): bool
    {
        return (bool) $this->is_internal;
    }
}
