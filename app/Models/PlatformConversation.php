<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DOMAIN B — Platform Communication Runtime model.
 *
 * INVARIANTS:
 * - No end-user records stored here.
 * - No support conversations.
 * - Participants: admin, platform_staff, customer_admin only.
 */
class PlatformConversation extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'type',
        'status',
        'subject',
        'priority',
        'created_by',
        'created_by_type',
        'metadata',
        'last_message_at',
    ];

    protected $casts = [
        'metadata'        => 'array',
        'last_message_at' => 'datetime',
    ];

    const TYPE_DIRECT       = 'direct';
    const TYPE_GROUP        = 'group';
    const TYPE_ANNOUNCEMENT = 'announcement';

    const STATUS_ACTIVE   = 'active';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_CLOSED   = 'closed';

    // Valid participant types for DOMAIN B — end_user is explicitly forbidden
    const ALLOWED_PARTICIPANT_TYPES = ['admin', 'platform_staff', 'customer_admin'];

    public function participants(): HasMany
    {
        return $this->hasMany(PlatformConversationParticipant::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PlatformMessage::class, 'conversation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PlatformAttachment::class, 'conversation_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForParticipant($query, int $participantId, string $participantType)
    {
        return $query->whereHas('participants', function ($q) use ($participantId, $participantType) {
            $q->where('participant_id', $participantId)
              ->where('participant_type', $participantType)
              ->whereNull('left_at');
        });
    }
}
