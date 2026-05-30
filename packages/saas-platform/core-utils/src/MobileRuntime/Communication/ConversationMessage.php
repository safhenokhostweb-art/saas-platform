<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Communication;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ConversationMessage extends Model
{
    use HasUuids;

    protected $table = 'mobile_conversation_messages';

    public const TYPE_TEXT         = 'text';
    public const TYPE_IMAGE        = 'image';
    public const TYPE_FILE         = 'file';
    public const TYPE_VOICE        = 'voice';
    public const TYPE_SYSTEM       = 'system';
    public const TYPE_AI_GENERATED = 'ai_generated';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_type',
        'message_type',
        'content',
        'metadata',
        'reply_to_id',
        'is_deleted',
        'deleted_at',
        'delivered_at',
        'read_by',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'read_by'      => 'array',
        'is_deleted'   => 'boolean',
        'deleted_at'   => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function scopeVisible($query)
    {
        return $query->where('is_deleted', false);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function getPreview(): string
    {
        $content = $this->content ?? '';
        return mb_strlen($content) > 100 ? mb_substr($content, 0, 97) . '...' : $content;
    }

    public function isReadBy(int|string $userId): bool
    {
        return in_array((string) $userId, array_map('strval', $this->read_by ?? []), true);
    }
}
