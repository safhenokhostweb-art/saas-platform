<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInstallation extends Model
{
    protected $fillable = [
        'site_id',
        'remote_server_id',
        'installer_user_id',
        'install_mode',
        'status',
        'customer_company',
        'customer_email',
        'customer_domain',
        'package_version',
        'package_sha256',
        'current_stage',
        'completed_stages',
        'started_at',
        'completed_at',
        'failed_at',
        'failure_reason',
        'request_payload',
        'source_installation_id',
        'initial_baseline_at',
        'initial_baseline_arvan_key',
        'initial_baseline_sha256',
        'post_install_validation',
    ];

    protected $casts = [
        'completed_stages'    => 'array',
        'request_payload'     => 'array',
        'post_install_validation' => 'array',
        'started_at'          => 'datetime',
        'completed_at'        => 'datetime',
        'failed_at'           => 'datetime',
        'initial_baseline_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(CustomerInstallationLog::class, 'installation_id');
    }

    public function rollbacks(): HasMany
    {
        return $this->hasMany(CustomerInstallRollback::class, 'installation_id')
                    ->orderByDesc('id');
    }

    public function remoteServer(): BelongsTo
    {
        return $this->belongsTo(CustomerRemoteServer::class, 'remote_server_id');
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['pending', 'running', 'rolling_back']);
    }

    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'rollback_failed']);
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
