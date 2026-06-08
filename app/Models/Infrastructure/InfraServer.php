<?php
namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InfraServer extends Model
{
    use SoftDeletes;

    protected $table = 'infra_servers';

    protected $fillable = [
        'name', 'hostname', 'ip_address', 'ipv6_address', 'environment',
        'roles', 'provider', 'datacenter', 'cpu_cores', 'ram_gb', 'disk_gb',
        'monthly_cost', 'currency', 'os', 'status', 'last_checked_at',
        'uptime_percent', 'last_backup_at', 'last_deployment_at', 'notes', 'is_active',
    ];

    protected $casts = [
        'roles'               => 'array',
        'monthly_cost'        => 'decimal:2',
        'last_checked_at'     => 'datetime',
        'last_backup_at'      => 'datetime',
        'last_deployment_at'  => 'datetime',
        'is_active'           => 'boolean',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(InfraDomain::class, 'server_id');
    }

    public function databaseServers(): HasMany
    {
        return $this->hasMany(InfraDatabaseServer::class, 'server_id');
    }

    public function hosting(): HasMany
    {
        return $this->hasMany(InfraHosting::class, 'server_id');
    }

    public function getRolesLabelAttribute(): string
    {
        $labels = [
            'application' => 'اپلیکیشن',
            'database'    => 'دیتابیس',
            'storage'     => 'استوریج',
            'cache'       => 'کش',
            'queue'       => 'صف پیام',
        ];
        $roles = $this->roles ?? [];
        return implode(', ', array_map(fn($r) => $labels[$r] ?? $r, $roles));
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
