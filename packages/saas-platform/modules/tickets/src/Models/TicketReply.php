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
        'customer_email',
        'customer_name',
        'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
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
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Check if the reply is from staff.
     */
    public function isFromStaff(): bool
    {
        return $this->author_type === self::AUTHOR_STAFF;
    }
}
