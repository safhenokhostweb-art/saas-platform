<?php
namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfraDatabaseServer extends Model
{
    use SoftDeletes;

    protected $table = 'infra_database_servers';

    protected $fillable = [
        'name', 'server_id', 'engine', 'version', 'host', 'port', 'environment',
        'size_mb', 'size_mb_prev_month', 'active_connections', 'max_connections',
        'status', 'last_backup_at', 'backup_storage', 'backup_frequency',
        'last_checked_at', 'replication_enabled', 'replication_role', 'notes', 'is_active',
    ];

    protected $casts = [
        'last_backup_at'       => 'datetime',
        'last_checked_at'      => 'datetime',
        'replication_enabled'  => 'boolean',
        'is_active'            => 'boolean',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(InfraServer::class, 'server_id');
    }

    public function getSizeGbAttribute(): ?float
    {
        return $this->size_mb ? round($this->size_mb / 1024, 2) : null;
    }

    public function getMonthlyGrowthMbAttribute(): ?int
    {
        if ($this->size_mb === null || $this->size_mb_prev_month === null) {
            return null;
        }
        return $this->size_mb - $this->size_mb_prev_month;
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'online'      => 'green',
            'degraded'    => 'yellow',
            'maintenance' => 'blue',
            'offline'     => 'red',
            default       => 'gray',
        };
    }
}
