<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PlatformSiteAgent extends Model
{
    protected $fillable = [
        'site_id',
        'provisioned_by',
        'mobile',
        'agent_password_enc',
        'customer_site_user_id',
        'is_active',
        'provisioned_at',
        'last_access_logged_at',
        'notes',
    ];

    protected $casts = [
        'is_active'              => 'boolean',
        'provisioned_at'         => 'datetime',
        'last_access_logged_at'  => 'datetime',
    ];

    protected $hidden = ['agent_password_enc'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function provisionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provisioned_by');
    }

    public function getDecryptedPassword(): ?string
    {
        if (! $this->agent_password_enc) {
            return null;
        }
        try {
            return Crypt::decryptString($this->agent_password_enc);
        } catch (\Throwable) {
            return null;
        }
    }
}
