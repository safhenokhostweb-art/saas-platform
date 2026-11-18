<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerInstallationPackage extends Model
{
    public const MINIMUM_SUPPORTED_VERSION = '1.1.0';

    protected $fillable = [
        'version',
        'arvan_key',
        'local_path',
        'sha256',
        'manifest_checksum',
        'size_bytes',
        'released_at',
        'is_active',
        'release_notes',
    ];

    protected $casts = [
        'released_at' => 'datetime',
        'is_active'   => 'boolean',
        'size_bytes'  => 'integer',
    ];

    public static function active(): ?self
    {
        return static::where('is_active', true)->latest('released_at')->first();
    }

    public function activate(): void
    {
        static::where('is_active', true)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    public function isStale(): bool
    {
        return version_compare($this->version, self::MINIMUM_SUPPORTED_VERSION, '<');
    }

    public function sizeHuman(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        }
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        }
        return round($bytes / 1024, 2) . ' KB';
    }
}
