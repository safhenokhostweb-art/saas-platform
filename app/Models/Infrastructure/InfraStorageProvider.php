<?php
namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InfraStorageProvider extends Model
{
    use SoftDeletes;

    protected $table = 'infra_storage_providers';

    protected $fillable = [
        'name', 'driver', 'endpoint', 'bucket', 'region', 'disk_name', 'purpose',
        'capacity_gb', 'used_gb', 'used_gb_prev_month', 'monthly_cost', 'currency',
        'status', 'last_measured_at', 'notes', 'is_active',
    ];

    protected $casts = [
        'monthly_cost'     => 'decimal:2',
        'last_measured_at' => 'datetime',
        'is_active'        => 'boolean',
    ];

    public function getUsagePercentAttribute(): ?float
    {
        if (!$this->capacity_gb || !$this->used_gb) {
            return null;
        }
        return round(($this->used_gb / $this->capacity_gb) * 100, 1);
    }

    public function getMonthlyGrowthGbAttribute(): ?int
    {
        if ($this->used_gb === null || $this->used_gb_prev_month === null) {
            return null;
        }
        return $this->used_gb - $this->used_gb_prev_month;
    }
}
