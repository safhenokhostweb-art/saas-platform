<?php
namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class InfraSchedulerRun extends Model
{
    protected $table = 'infra_scheduler_runs';

    protected $fillable = [
        'command', 'status', 'started_at', 'completed_at',
        'duration_ms', 'output', 'error',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function isSuccess(): bool  { return $this->status === 'success'; }
    public function isFailed(): bool   { return $this->status === 'failed'; }
    public function isRunning(): bool  { return $this->status === 'running'; }

    public function scopeByCommand(Builder $query, string $command): Builder
    {
        return $query->where('command', $command);
    }

    public static function latestByCommand(string $command): ?self
    {
        return static::where('command', $command)->orderByDesc('started_at')->first();
    }

    public static function startRun(string $command): self
    {
        return static::create([
            'command'    => $command,
            'status'     => 'running',
            'started_at' => now(),
        ]);
    }

    public function endSuccess(?string $output = null): void
    {
        $this->update([
            'status'       => 'success',
            'completed_at' => now(),
            'duration_ms'  => (int) ($this->started_at->diffInMilliseconds(now())),
            'output'       => $output ? mb_substr($output, 0, 65000) : null,
        ]);
    }

    public function endFailed(string $error): void
    {
        $this->update([
            'status'       => 'failed',
            'completed_at' => now(),
            'duration_ms'  => (int) ($this->started_at->diffInMilliseconds(now())),
            'error'        => mb_substr($error, 0, 65000),
        ]);
    }

    public static function recentSummary(): array
    {
        $tracked = [
            'monitor:sites',
            'monitor:platform',
            'db:backup --retention=7',
            'education:generate-content',
            'education:generate-gap-content',
            'education:refresh-recommendations',
            'runtime:scan-anomalies',
        ];

        $result = [];
        foreach ($tracked as $cmd) {
            $run = static::latestByCommand($cmd);
            $result[] = [
                'command'      => $cmd,
                'status'       => $run?->status ?? 'never',
                'started_at'   => $run?->started_at,
                'duration_ms'  => $run?->duration_ms,
                'has_error'    => $run?->isFailed() ?? false,
            ];
        }
        return $result;
    }
}
