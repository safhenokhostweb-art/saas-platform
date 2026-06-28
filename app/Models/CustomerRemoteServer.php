<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerRemoteServer extends Model
{
    protected $fillable = [
        'label',
        'type',
        'host',
        'port',
        'username',
        'auth_method',
        'private_key_encrypted',
        'password_encrypted',
        'api_token_encrypted',
        'key_fingerprint',
        'path',
        'domain',
        'ssh_key_id',
        'key_installed_at',
        'api_token_created_at',
        'api_token_last_used_at',
        'last_tested_at',
        'last_test_result',
    ];

    protected $hidden = [
        'private_key_encrypted',
        'password_encrypted',
        'api_token_encrypted',
    ];

    protected $casts = [
        'last_tested_at'        => 'datetime',
        'key_installed_at'      => 'datetime',
        'api_token_created_at'  => 'datetime',
        'api_token_last_used_at'=> 'datetime',
    ];

    public function installations(): HasMany
    {
        return $this->hasMany(CustomerInstallation::class, 'remote_server_id');
    }

    public function isKeyAuth(): bool
    {
        return $this->auth_method === 'key';
    }

    public function wasTestedSuccessfully(): bool
    {
        return $this->last_test_result === 'success';
    }
}
