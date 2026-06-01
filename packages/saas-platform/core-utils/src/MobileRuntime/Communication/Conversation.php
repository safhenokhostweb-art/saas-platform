<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Communication;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Conversation extends Model
{
    use HasUuids;

    protected $table = 'mobile_conversations';

    public const TYPE_DIRECT        = 'direct';
    public const TYPE_GROUP         = 'group';
    public const TYPE_SUPPORT       = 'support';
    public const TYPE_TICKET_LINKED = 'ticket_linked';

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_CLOSED   = 'closed';
    public const STATUS_MERGED   = 'merged';

    protected $fillable = [
        'tenant_id',
        'title',
        'type',
        'status',
        'metadata',
        'created_by',
        'merged_into_id',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForUser($query, int|string $userId)
    {
        return $query->whereHas('participants', fn ($q) => $q->where('user_id', $userId)->whereNull('left_at'));
    }

    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }

    public function messages()
    {
        return $this->hasMany(ConversationMessage::class, 'conversation_id')->orderBy('created_at');
    }

    public function attachments()
    {
        return $this->hasMany(ConversationAttachment::class, 'conversation_id');
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
