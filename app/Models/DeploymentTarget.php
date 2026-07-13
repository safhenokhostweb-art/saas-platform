<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\Enums\AuthMethod;

/**
 * Universal deployment target — replaces the legacy "server" concept.
 *
 * A deployment target abstracts any hosting environment regardless of type.
 * The connection_driver field determines which DeploymentDriver is loaded.
 * Capabilities are auto-computed from the driver type but can be overridden.
 *
 * @deprecated Phase 14-B1 — architecture scaffolding, not yet wired to any
 * production flow. Nothing in the app creates a row in this table (grep-
 * verified: no controller/form constructs a DeploymentTarget). The live
 * customer-installation pipeline still uses CustomerRemoteServer via
 * CustomerInstallation::remote_server_id. Left intact per the 14-B1 audit —
 * do not migrate CustomerInstallation to deployment_target_id without a
 * dedicated phase (see docs/architecture Phase 14-B report, section 4).
 * TODO: either give this model a real caller (admin UI + pipeline wiring)
 * or remove it together with DeploymentOrchestrator.
 */
class DeploymentTarget extends Model
{
    protected $fillable = [
        'name',
        'type',               // vps, shared_hosting, directadmin, cpanel, docker, managed_cloud
        'connection_driver',  // ssh, cpanel, directadmin, sftp, docker, fake
        'auth_method',        // ssh_key, ssh_password, cpanel_api_token, directadmin_api_token, sftp_password
        'host',
        'port',
        'username',
        'path',               // web root on remote (e.g. /var/www/customer-site)
        'domain',             // primary domain served by this target
        'status',             // active, inactive, pending, error
        'private_key_encrypted',
        'password_encrypted',
        'api_token_encrypted',
        'key_fingerprint',
        'capabilities',       // JSON override; null = auto from driver type
        'last_tested_at',
        'last_test_result',
        'notes',
    ];

    protected $hidden = [
        'private_key_encrypted',
        'password_encrypted',
        'api_token_encrypted',
    ];

    protected $casts = [
        'capabilities'   => 'array',
        'last_tested_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function installations(): HasMany
    {
        return $this->hasMany(CustomerInstallation::class, 'deployment_target_id');
    }

    // ─── Driver resolution ────────────────────────────────────────────────────

    public function driverType(): DriverType
    {
        return DriverType::from($this->connection_driver ?? 'ssh');
    }

    public function authMethod(): AuthMethod
    {
        return AuthMethod::from($this->auth_method ?? 'ssh_key');
    }

    /**
     * Resolve capabilities: use persisted JSON override if present,
     * otherwise derive from driver type defaults.
     */
    public function resolvedCapabilities(): DriverCapabilities
    {
        if (!empty($this->capabilities)) {
            return DriverCapabilities::fromArray($this->capabilities);
        }

        return match($this->driverType()) {
            DriverType::SSH         => DriverCapabilities::forVps(),
            DriverType::CPANEL      => DriverCapabilities::forCpanel(),
            DriverType::DIRECTADMIN => DriverCapabilities::forDirectAdmin(),
            DriverType::SFTP        => DriverCapabilities::forSftp(),
            DriverType::DOCKER      => DriverCapabilities::forDocker(),
            default                 => new DriverCapabilities(),
        };
    }

    // ─── Auth helpers ─────────────────────────────────────────────────────────

    public function isKeyAuth(): bool
    {
        return $this->auth_method === AuthMethod::SSH_KEY->value;
    }

    public function isApiTokenAuth(): bool
    {
        return in_array($this->auth_method, [
            AuthMethod::CPANEL_API_TOKEN->value,
            AuthMethod::DIRECTADMIN_API_TOKEN->value,
        ]);
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function wasTestedSuccessfully(): bool
    {
        return $this->last_test_result === 'success';
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByDriver($query, DriverType $type)
    {
        return $query->where('connection_driver', $type->value);
    }
}
