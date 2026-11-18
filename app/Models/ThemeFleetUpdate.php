<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeFleetUpdate extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    protected $fillable = [
        'site_id',
        'theme_id',
        'from_release_id',
        'to_release_id',
        'status',
        'revision_number',
        'reconciliation_summary',
        'failure_reason',
        'dispatched_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'reconciliation_summary' => 'array',
        'started_at'             => 'datetime',
        'completed_at'           => 'datetime',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }

    public function fromRelease()
    {
        return $this->belongsTo(ThemeRelease::class, 'from_release_id');
    }

    public function toRelease()
    {
        return $this->belongsTo(ThemeRelease::class, 'to_release_id');
    }

    public function dispatchedBy()
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }
}
