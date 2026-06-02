<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOMAIN B participant — only admin|platform_staff|customer_admin allowed.
 */
class PlatformConversationParticipant extends Model
{
    protected $fillable = [
        'conversation_id',
        'participant_id',
        'participant_type',
        'role',
        'display_name',
        'joined_at',
        'left_at',
        'last_read_at',
        'unread_count',
    ];

    protected $casts = [
        'joined_at'    => 'datetime',
        'left_at'      => 'datetime',
        'last_read_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    const TYPE_ADMIN          = 'admin';
    const TYPE_PLATFORM_STAFF = 'platform_staff';
    const TYPE_CUSTOMER_ADMIN = 'customer_admin';

    // Explicitly NOT allowed — document what is forbidden
    const FORBIDDEN_TYPES = ['end_user', 'user', 'tenant_user'];

    const ROLE_OWNER       = 'owner';
    const ROLE_PARTICIPANT = 'participant';
    const ROLE_OBSERVER    = 'observer';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (in_array($model->participant_type, self::FORBIDDEN_TYPES, true)) {
                throw new \InvalidArgumentException(
                    "Participant type '{$model->participant_type}' is not allowed in platform conversations."
                );
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(PlatformConversation::class, 'conversation_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }
}
