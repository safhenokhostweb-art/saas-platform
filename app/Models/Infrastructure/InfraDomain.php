<?php
namespace App\Models\Infrastructure;

use App\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfraDomain extends Model
{
    use SoftDeletes;

    protected $table = 'infra_domains';

    protected $fillable = [
        'domain', 'type', 'site_id', 'server_id', 'registrar',
        'registered_at', 'expires_at', 'ssl_enabled', 'ssl_provider',
        'ssl_expires_at', 'ssl_status', 'dns_provider', 'dns_notes',
        'status', 'redirects_to_https', 'notes',
    ];

    protected $casts = [
        'registered_at'       => 'date',
        'expires_at'          => 'date',
        'ssl_expires_at'      => 'date',
        'ssl_enabled'         => 'boolean',
        'redirects_to_https'  => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(InfraServer::class, 'server_id');
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        return $this->expires_at ? now()->diffInDays($this->expires_at, false) : null;
    }

    public function getDaysUntilSslExpiryAttribute(): ?int
    {
        return $this->ssl_expires_at ? now()->diffInDays($this->ssl_expires_at, false) : null;
    }

    public function getIsExpiringAttribute(): bool
    {
        return $this->days_until_expiry !== null && $this->days_until_expiry <= 30;
    }

    public function getIsSslExpiringAttribute(): bool
    {
        return $this->days_until_ssl_expiry !== null && $this->days_until_ssl_expiry <= 14;
    }
}
