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
        'table_coverage', 'completeness_status',
    ];

    protected $casts = [
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
        'file_size_mb'   => 'float',
        'table_coverage' => 'array',
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

    /**
     * @param array{included_tables?: list<string>, skipped_tables?: list<array{table: string, reason: string}>, insert_counts?: array<string, int>, total_insert_count?: int, completeness_status?: string} $coverage
     */
    public function endSuccess(string $filePath, float $sizeMb, int $durationSec, array $coverage = []): void
    {
        $this->update([
            'status'              => 'success',
            'file_path'           => $filePath,
            'file_size_mb'        => $sizeMb,
            'duration_seconds'    => $durationSec,
            'completed_at'        => now(),
            'table_coverage'      => $coverage ?: null,
            'completeness_status' => $coverage['completeness_status'] ?? null,
        ]);
    }

    public function endFailed(string $error, array $coverage = []): void
    {
        $this->update([
            'status'              => 'failed',
            'error_message'       => $error,
            'completed_at'        => now(),
            'table_coverage'      => $coverage ?: null,
            'completeness_status' => $coverage['completeness_status'] ?? ($coverage ? 'failed' : null),
        ]);
    }
}
