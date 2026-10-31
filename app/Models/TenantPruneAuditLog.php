<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPruneAuditLog extends Model
{
    public const TRIGGER_AUTO  = 'auto_agent';
    public const TRIGGER_ADMIN = 'admin_user';

    public const MODE_DRY_RUN = 'dry_run';
    public const MODE_EXECUTE = 'execute';

    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL   = 'partial';
    public const STATUS_FAILED    = 'failed';

    protected $table = 'tenant_prune_audit_logs';

    protected $fillable = [
        'site_id',
        'triggered_by',
        'user_id',
        'mode',
        'profile',
        'status',
        'reclaimed_bytes',
        'pruned_items_count',
        'details',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'details'            => 'array',
        'reclaimed_bytes'    => 'integer',
        'pruned_items_count' => 'integer',
        'started_at'         => 'datetime',
        'finished_at'        => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDryRun(): bool
    {
        return $this->mode === self::MODE_DRY_RUN;
    }
}
