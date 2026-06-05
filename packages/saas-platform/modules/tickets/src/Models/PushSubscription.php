<?php

namespace SaaSPlatform\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SUPPORT-SUITE-01 — Section E
 * Web Push subscription record stored per browser/device.
 */
class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';

    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'encoding',
        'last_used_at',
    ];

    public static function findByEndpoint(string $endpoint): ?self
    {
        return static::where('endpoint_hash', hash('sha256', $endpoint))->first();
    }

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('tickets.user_model'));
    }
}
