<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteLifecycleInvoice extends Model
{
    const TYPE_REACTIVATION    = 'reactivation';
    const TYPE_ARCHIVE_RESTORE = 'archive_restore';
    const TYPE_STORAGE_RETENTION = 'storage_retention';
    const TYPE_OVERAGE         = 'storage_overage';

    const STATUS_PENDING   = 'pending';
    const STATUS_PAID      = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'site_id', 'invoice_number', 'type', 'amount', 'status',
        'due_at', 'paid_at', 'payment_reference', 'line_items',
    ];

    protected $casts = [
        'line_items' => 'array',
        'due_at'     => 'datetime',
        'paid_at'    => 'datetime',
        'amount'     => 'integer',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'active'       => 'فعال',
            'grace_period' => 'دوره اعتبار',
            'suspended'    => 'معلق',
            'archived'     => 'آرشیو',
            'deleted'      => 'حذف‌شده',
            default        => $status,
        };
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_REACTIVATION    => 'هزینه فعال‌سازی مجدد',
            self::TYPE_ARCHIVE_RESTORE => 'هزینه بازگردانی آرشیو',
            self::TYPE_STORAGE_RETENTION => 'هزینه نگهداری',
            self::TYPE_OVERAGE           => 'هزینه مازاد فضا',
            default                    => $this->type,
        };
    }

    public static function generateNumber(): string
    {
        return 'LCI-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
}
