<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInstallationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'installation_id',
        'stage',
        'event',
        'message',
        'output',
        'duration_ms',
        'created_at',
    ];

    protected $casts = [
        'output'     => 'array',
        'created_at' => 'datetime',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(CustomerInstallation::class, 'installation_id');
    }

    protected static function booted(): void
    {
        // Immutable: no updates or deletes allowed
        static::updating(fn() => false);
        static::deleting(fn() => false);
    }
}
