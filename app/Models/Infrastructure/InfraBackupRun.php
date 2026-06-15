<?php
namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class InfraBackupRun extends Model
{
    protected $table = 'infra_backup_runs';

    protected $fillable = [
        'backup_type', 'site_id', 'status', 'file_path',
        'file_size_mb', 'duration_seconds', 'error_message',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'file_size_mb' => 'float',
    ];

    public function site()
    {
        return $this->belongsTo(\App\Models\Site::class);
    }

    public function isSuccess(): bool  { return $this->status === 'success'; }
    public function isFailed(): bool   { return $this->status === 'failed'; }
    public function isRunning(): bool  { return $this->status === 'running'; }

    public function scopeSuccess(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopePlatform(Builder $query): Builder
    {
        return $query->whereNull('site_id');
    }

    public static function latestPlatform(): ?self
    {
        return static::whereNull('site_id')
            ->where('backup_type', 'database')
            ->orderByDesc('started_at')
            ->first();
    }

    public static function startRun(string $type = 'database', ?int $siteId = null): self
    {
        return static::create([
            'backup_type' => $type,
            'site_id'     => $siteId,
            'status'      => 'running',
            'started_at'  => now(),
        ]);
    }

    public function endSuccess(string $filePath, float $sizeMb, int $durationSec): void
    {
        $this->update([
            'status'           => 'success',
            'file_path'        => $filePath,
            'file_size_mb'     => $sizeMb,
            'duration_seconds' => $durationSec,
            'completed_at'     => now(),
        ]);
    }

    public function endFailed(string $error): void
    {
        $this->update([
            'status'        => 'failed',
            'error_message' => $error,
            'completed_at'  => now(),
        ]);
    }
}
