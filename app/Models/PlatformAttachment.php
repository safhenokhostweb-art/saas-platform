<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * DOMAIN B attachment — stored on saas-platform filesystem only.
 * No shared paths with tenant attachment directories.
 */
class PlatformAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'conversation_id',
        'original_name',
        'stored_path',
        'disk',
        'mime_type',
        'file_size',
        'file_hash',
        'uploader_id',
        'uploader_type',
        'is_deleted',
    ];

    protected $casts = [
        'file_size'  => 'integer',
        'is_deleted' => 'boolean',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(PlatformMessage::class, 'message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(PlatformConversation::class, 'conversation_id');
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->stored_path);
    }

    public function isStoredInPlatformDir(): bool
    {
        return str_starts_with($this->stored_path, 'platform-attachments/');
    }

    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }
}
