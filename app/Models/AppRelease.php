<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppRelease extends Model
{
    protected $fillable = [
        'platform', 'version', 'build_number', 'file_path', 'file_size',
        'file_name', 'mime_type', 'release_notes', 'is_latest',
        'download_count', 'uploaded_by',
    ];

    protected $casts = [
        'is_latest'      => 'boolean',
        'file_size'      => 'integer',
        'build_number'   => 'integer',
        'download_count' => 'integer',
    ];

    public const PLATFORMS = [
        'android' => 'Android (APK)',
        'windows' => 'Windows (EXE)',
        'macos'   => 'macOS (DMG)',
        'linux'   => 'Linux (AppImage)',
    ];

    public const PLATFORM_ICONS = [
        'android' => '🤖',
        'windows' => '🪟',
        'macos'   => '🍎',
        'linux'   => '🐧',
    ];

    public const ACCEPTED_MIMES = [
        'android' => ['application/vnd.android.package-archive', 'application/octet-stream'],
        'windows' => ['application/x-msdownload', 'application/octet-stream', 'application/zip', 'application/x-zip-compressed'],
        'macos'   => ['application/x-apple-diskimage', 'application/octet-stream', 'application/zip'],
        'linux'   => ['application/octet-stream', 'application/x-executable'],
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('created_at');
    }

    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function getSizeLabelAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024)         return "{$bytes} B";
        if ($bytes < 1024 ** 2)    return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 ** 3)    return round($bytes / 1024 ** 2, 1) . ' MB';
        return round($bytes / 1024 ** 3, 2) . ' GB';
    }

    public function getPlatformLabelAttribute(): string
    {
        return self::PLATFORMS[$this->platform] ?? $this->platform;
    }

    public function getPlatformIconAttribute(): string
    {
        return self::PLATFORM_ICONS[$this->platform] ?? '📦';
    }
}
