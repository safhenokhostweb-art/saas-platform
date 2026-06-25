<?php
namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestoreLog extends Model
{
    use HasFactory;
    protected $table = 'restore_logs';

    protected $fillable = [
        'restore_type',
        'site_id',
        'backup_file',
        'status',
        'dry_run',
        'restore_token',
        'validation_result',
        'execution_log',
        'rollback_snapshot_path',
        'executed_by',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'dry_run'           => 'boolean',
        'validation_result' => 'array',
        'execution_log'     => 'array',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
    ];

    const TYPE_TENANT_DATABASE    = 'tenant_database';
    const TYPE_TENANT_STORAGE     = 'tenant_storage';
    const TYPE_PLATFORM_DATABASE  = 'platform_database';
    const TYPE_PLATFORM_STORAGE   = 'platform_storage';

    const STATUS_PENDING          = 'pending';
    const STATUS_RUNNING          = 'running';
    const STATUS_SUCCESS          = 'success';
    const STATUS_FAILED           = 'failed';
    const STATUS_DRY_RUN_COMPLETE = 'dry_run_complete';

    public function site()
    {
        return $this->belongsTo(\App\Models\Site::class);
    }

    public function executor()
    {
        return $this->belongsTo(\App\Models\User::class, 'executed_by');
    }

    public function markRunning(): void
    {
        $this->update(['status' => self::STATUS_RUNNING, 'started_at' => now()]);
    }

    public function markSuccess(array $log = []): void
    {
        $this->update([
            'status'       => self::STATUS_SUCCESS,
            'completed_at' => now(),
            'execution_log'=> $log ?: $this->execution_log,
        ]);
    }

    public function markFailed(string $error, array $log = []): void
    {
        $this->update([
            'status'        => self::STATUS_FAILED,
            'error_message' => $error,
            'completed_at'  => now(),
            'execution_log' => $log ?: $this->execution_log,
        ]);
    }

    public function typeLabelFa(): string
    {
        return match($this->restore_type) {
            self::TYPE_TENANT_DATABASE   => 'بازیابی دیتابیس تِنِنت',
            self::TYPE_TENANT_STORAGE    => 'بازیابی فایل‌های تِنِنت',
            self::TYPE_PLATFORM_DATABASE => 'بازیابی دیتابیس پلتفرم',
            self::TYPE_PLATFORM_STORAGE  => 'بازیابی فایل‌های پلتفرم',
            default                      => $this->restore_type,
        };
    }

    public function statusLabelFa(): string
    {
        return match($this->status) {
            self::STATUS_PENDING          => 'در انتظار',
            self::STATUS_RUNNING          => 'در حال اجرا',
            self::STATUS_SUCCESS          => 'موفق',
            self::STATUS_FAILED           => 'ناموفق',
            self::STATUS_DRY_RUN_COMPLETE => 'شبیه‌سازی کامل',
            default                       => $this->status,
        };
    }
}
