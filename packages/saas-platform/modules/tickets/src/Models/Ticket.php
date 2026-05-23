<?php

namespace SaaSPlatform\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'closed_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'attachments' => 'array',
    ];

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';

    /**
     * Get the customer that owns the ticket.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'customer_id');
    }

    /**
     * Get the assignee of the ticket.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    /**
     * Get the site that owns the ticket.
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Site::class, 'site_id');
    }

    /**
     * Get the replies for the ticket.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'ticket_id')->orderBy('created_at');
    }
}
