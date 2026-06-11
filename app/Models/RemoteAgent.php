<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;

class RemoteAgent extends Model
{
    protected $fillable = [
        'name', 'base_url', 'secret_key', 'environment',
        'is_active', 'last_health_status', 'last_health_checked_at',
        'last_health_data', 'server_id', 'notes',
    ];

    protected $casts = [
        'is_active'              => 'boolean',
        'last_health_checked_at' => 'datetime',
        'last_health_data'       => 'array',
    ];

    protected $hidden = ['secret_key'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function getDecryptedSecret(): string
    {
        return Crypt::decryptString($this->secret_key);
    }

    public function setSecretKeyAttribute(string $value): void
    {
        $this->attributes['secret_key'] = Crypt::encryptString($value);
    }

    public function isHealthy(): bool
    {
        return $this->last_health_status === 'healthy';
    }

    public function statusBadgeColor(): string
    {
        return match ($this->last_health_status) {
            'healthy'  => 'green',
            'degraded' => 'amber',
            'offline'  => 'red',
            default    => 'gray',
        };
    }
}
