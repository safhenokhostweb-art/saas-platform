<?php

namespace SaaSPlatform\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SUPPORT-SUITE-01 — Section N
 * Customer upvote on a Feature Request ticket.
 */
class TicketVote extends Model
{
    protected $table = 'ticket_votes';

    protected $fillable = [
        'ticket_id',
        'voter_id',
        'voter_mobile',
        'voter_name',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
