<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDeletion extends Model
{
    protected $fillable = [
        'installation_id',
        'site_id',
        'initiated_by',
        'policy',
        'status',
        'current_stage',
        'completed_stages',
        'audit_snapshot',
        'verify_report',
        'failure_reason',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'completed_stages' => 'array',
        'audit_snapshot'   => 'array',
        'verify_report'    => 'array',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
        'failed_at'        => 'datetime',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(CustomerInstallation::class, 'installation_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CustomerDeletionLog::class, 'deletion_id')->orderBy('id');
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['pending', 'running']);
    }

    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function completedStagesList(): array
    {
        return $this->completed_stages ?? [];
    }

    public function hasCompletedStage(string $stage): bool
    {
        return in_array($stage, $this->completedStagesList());
    }

    public function markStageComplete(string $stage): void
    {
        $stages = $this->completedStagesList();
        if (!in_array($stage, $stages)) {
            $stages[] = $stage;
        }
        $this->update([
            'current_stage'    => $stage,
            'completed_stages' => $stages,
        ]);
    }
}
