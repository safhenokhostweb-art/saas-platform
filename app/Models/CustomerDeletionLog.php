<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDeletionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'deletion_id',
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

    public function deletion(): BelongsTo
    {
        return $this->belongsTo(CustomerDeletion::class, 'deletion_id');
    }
}
