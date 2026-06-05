<?php

namespace SaaSPlatform\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SUPPORT-SUITE-01 — Section N
 * Customer subscription to updates on a Feature Request.
 */
class TicketFrSubscriber extends Model
{
    protected $table = 'ticket_fr_subscribers';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'customer_mobile',
        'customer_name',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
