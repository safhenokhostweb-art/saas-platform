<?php

namespace App\Models\Infrastructure;

use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class DnsProviderSetting extends Model
{
    protected $table = 'dns_provider_settings';

    protected $fillable = [
        'provider',
        'root_domain',
        'api_token_encrypted',
        'api_base_url',
        'is_active',
        'cloud_proxy',
        'default_ttl',
        'health_status',
        'health_message',
        'health_checked_at',
    ];

    protected $hidden = ['api_token_encrypted'];

    protected $casts = [
        'is_active'        => 'boolean',
        'cloud_proxy'      => 'boolean',
        'default_ttl'      => 'integer',
        'health_checked_at'=> 'datetime',
    ];

    /** Returns the first active DNS provider setting, or null if not configured. */
    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /** Encrypt and store a plain-text API token. */
    public function setToken(string $plainToken): void
    {
        $vault = app(CredentialVault::class);
        $this->api_token_encrypted = $vault->encrypt($plainToken);
    }

    /** Decrypt and return the API token. Throws if not set. */
    public function getDecryptedToken(): string
    {
        if (empty($this->api_token_encrypted)) {
            throw new RuntimeException('DNS provider API token is not configured.');
        }
        $vault = app(CredentialVault::class);
        return $vault->decrypt($this->api_token_encrypted);
    }

    /** True only if provider is configured with an encrypted token and is active. */
    public function isReady(): bool
    {
        return $this->is_active && !empty($this->api_token_encrypted) && !empty($this->root_domain);
    }

    public function healthBadge(): string
    {
        return match ($this->health_status) {
            'ok'        => 'badge-success',
            'error'     => 'badge-danger',
            default     => 'badge-secondary',
        };
    }
}
