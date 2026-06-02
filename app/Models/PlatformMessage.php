<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DOMAIN B message — stored only in saas-platform database.
 * Sender must be admin|platform_staff|customer_admin.
 */
class PlatformMessage extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_type',
        'message_type',
        'content',
        'reply_to_message_id',
        'is_deleted',
        'metadata',
        'edited_at',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'edited_at'  => 'datetime',
        'metadata'   => 'array',
    ];

    const TYPE_TEXT         = 'text';
    const TYPE_FILE         = 'file';
    const TYPE_SYSTEM       = 'system';
    const TYPE_ANNOUNCEMENT = 'announcement';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $forbidden = PlatformConversationParticipant::FORBIDDEN_TYPES;
            if (in_array($model->sender_type, $forbidden, true)) {
                throw new \InvalidArgumentException(
                    "Sender type '{$model->sender_type}' is not allowed in platform messages."
                );
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(PlatformConversation::class, 'conversation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PlatformAttachment::class, 'message_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_deleted', false)->whereNull('deleted_at');
    }
}
