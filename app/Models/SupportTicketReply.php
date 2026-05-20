<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicketReply extends Model
{
    use HasFactory;

    public const AUTHOR_STAFF = 'staff';
    public const AUTHOR_CUSTOMER = 'customer';

    protected $fillable = [
        'support_ticket_id', 'user_id', 'message',
        'author_type', 'customer_email', 'customer_name',
        'attachments',
    ];

    protected $casts = [
        'author_type' => 'string',
        'attachments' => 'array',
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
