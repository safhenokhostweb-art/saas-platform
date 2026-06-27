<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInstallRollback extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'installation_id',
        'stage',
        'snapshot',
        'executed_at',
        'created_at',
    ];

    protected $casts = [
        'snapshot'    => 'array',
        'executed_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(CustomerInstallation::class, 'installation_id');
    }

    public function isPending(): bool
    {
        return $this->executed_at === null;
    }

    public function markExecuted(): void
    {
        $this->update(['executed_at' => now()]);
    }
}
