<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Communication;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Storage;

class ConversationAttachment extends Model
{
    use HasUuids;

    protected $table = 'mobile_conversation_attachments';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'is_virus_scanned',
        'metadata',
    ];

    protected $casts = [
        'metadata'        => 'array',
        'is_virus_scanned' => 'boolean',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function message()
    {
        return $this->belongsTo(ConversationMessage::class, 'message_id');
    }

    public function getUrl(): string
    {
        return Storage::url($this->file_path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function getHumanFileSize(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
