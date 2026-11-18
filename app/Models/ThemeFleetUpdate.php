<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeFleetUpdate extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    public const TYPE_THEME       = 'theme';
    public const TYPE_PACKAGE     = 'package';
    public const TYPE_CANVAS      = 'canvas';
    public const TYPE_WIDGETS     = 'widgets';
    public const TYPE_DASHBOARD   = 'dashboard';
    public const TYPE_MOTION      = 'motion';
    public const TYPE_ROLLBACK    = 'rollback';

    protected $fillable = [
        'site_id',
        'update_type',
        'theme_id',
        'from_release_id',
        'to_release_id',
        'component_versions',
        'status',
        'is_rollback',
        'rollback_from_update_id',
        'revision_number',
        'reconciliation_summary',
        'changelog_summary',
        'failure_reason',
        'dispatched_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'component_versions'     => 'array',
        'reconciliation_summary' => 'array',
        'is_rollback'            => 'boolean',
        'started_at'             => 'datetime',
        'completed_at'           => 'datetime',
    ];

    public function typeLabel(): string
    {
        return match ($this->update_type) {
            self::TYPE_THEME     => 'قالب و پوسته',
            self::TYPE_PACKAGE   => 'پکیج هسته و داشبورد',
            self::TYPE_CANVAS    => 'موتور کانواس',
            self::TYPE_WIDGETS   => 'کتابخانه ویجت‌ها',
            self::TYPE_DASHBOARD => 'داشبورد مشتری',
            self::TYPE_MOTION    => 'موشن‌گرافیک',
            self::TYPE_ROLLBACK  => 'بازگشت به نسخه قبل (Rollback)',
            default              => $this->update_type ?? 'سایر',
        };
    }

    public function rollbackFrom()
    {
        return $this->belongsTo(self::class, 'rollback_from_update_id');
    }

    public function rollbacks()
    {
        return $this->hasMany(self::class, 'rollback_from_update_id');
    }

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
