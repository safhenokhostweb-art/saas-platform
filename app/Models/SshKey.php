<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SshKey extends Model
{
    protected $fillable = [
        'label',
        'key_type',
        'public_key',
        'private_key_encrypted',
        'passphrase_encrypted',
        'fingerprint',
        'deployment_target_id',
        'status',
        'installed_at',
        'last_used_at',
        'rotated_at',
        'notes',
    ];

    protected $hidden = [
        'private_key_encrypted',
        'passphrase_encrypted',
    ];

    protected $casts = [
        'installed_at'  => 'datetime',
        'last_used_at'  => 'datetime',
        'rotated_at'    => 'datetime',
    ];

    public function deploymentTarget(): BelongsTo
    {
        return $this->belongsTo(CustomerRemoteServer::class, 'deployment_target_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInstalled(): bool
    {
        return $this->installed_at !== null;
    }

    public function shortFingerprint(): string
    {
        // Returns last 16 chars for display
        return '...' . substr($this->fingerprint, -16);
    }

    public function keyTypeLabel(): string
    {
        return match($this->key_type) {
            'ed25519' => 'Ed25519 (پیشنهادی)',
            'rsa4096' => 'RSA-4096',
            default   => $this->key_type,
        };
    }

    public function publicKeyComment(): string
    {
        $parts = explode(' ', trim($this->public_key));
        return $parts[2] ?? '';
    }
}
