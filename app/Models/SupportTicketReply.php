<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @deprecated PHASE 7.1 — Use SaaSPlatform\Modules\Tickets\Models\TicketReply instead.
 *             References support_ticket_replies table. Canonical table is ticket_replies.
 */
class SupportTicketReply extends Model
{
    use HasFactory, SoftDeletes;

    public const AUTHOR_STAFF = 'staff';
    public const AUTHOR_CUSTOMER = 'customer';

    protected $fillable = [
        'support_ticket_id', 'user_id', 'message',
        'author_type', 'customer_mobile', 'customer_name',
        'attachments',
    ];

    protected $casts = [
        'author_type' => 'string',
        'attachments' => 'array',
        'deleted_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isFromStaff(): bool
    {
        return $this->author_type === self::AUTHOR_STAFF;
    }
}
