<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 13-O9a — encrypted per-installation credential storage.
 * `encrypted_value` is always ciphertext produced by CredentialVault;
 * never store or read a plaintext value through this model.
 */
class InstallationCredential extends Model
{
    protected $fillable = [
        'installation_id',
        'type',
        'encrypted_value',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(CustomerInstallation::class, 'installation_id');
    }
}
