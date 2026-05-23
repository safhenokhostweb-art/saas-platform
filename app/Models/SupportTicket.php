<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'site_id', 'ticket_number', 'subject', 'message', 'status', 'priority',
        'support_level', 'customer_email', 'customer_name',
        'assigned_to', 'closed_at', 'first_response_at', 'resolved_at',
        'category', 'tags', 'attachments',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'deleted_at' => 'datetime',
        'attachments' => 'array',
    ];

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = self::generateTicketNumber();
            }
        });
    }

    public static function generateTicketNumber(): string
    {
        $year = now()->format('Y');
        $count = static::withTrashed()->whereYear('created_at', $year)->count();
        return sprintf('ST-%s-%05d', $year, $count + 1);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies()
    {
        return $this->hasMany(SupportTicketReply::class)->orderBy('created_at');
    }
}
