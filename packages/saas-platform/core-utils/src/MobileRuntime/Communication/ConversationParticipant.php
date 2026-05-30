<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Communication;

use Illuminate\Database\Eloquent\Model;

class ConversationParticipant extends Model
{
    protected $table = 'mobile_conversation_participants';

    public const USER_TYPE_PLATFORM_ADMIN = 'platform_admin';
    public const USER_TYPE_TENANT_ADMIN   = 'tenant_admin';
    public const USER_TYPE_SUPPORT_AGENT  = 'support_agent';
    public const USER_TYPE_END_USER       = 'end_user';
    public const USER_TYPE_AI_ASSISTANT   = 'ai_assistant';

    public const ROLE_OWNER       = 'owner';
    public const ROLE_PARTICIPANT = 'participant';
    public const ROLE_OBSERVER    = 'observer';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'user_type',
        'role',
        'joined_at',
        'left_at',
        'last_read_at',
        'is_muted',
        'metadata',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'is_muted'     => 'boolean',
        'joined_at'    => 'datetime',
        'left_at'      => 'datetime',
        'last_read_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
