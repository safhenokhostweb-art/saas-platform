<?php
namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfraHosting extends Model
{
    use SoftDeletes;

    protected $table = 'infra_hosting';

    protected $fillable = [
        'name', 'server_id', 'provider', 'plan', 'account_email', 'panel_url',
        'environment', 'cpu_cores_allocated', 'ram_gb_allocated', 'disk_gb_allocated',
        'bandwidth_gb_allocated', 'monthly_cost', 'currency', 'billing_cycle_start',
        'billing_cycle_end', 'next_renewal_at', 'status', 'notes',
    ];

    protected $casts = [
        'billing_cycle_start' => 'date',
        'billing_cycle_end'   => 'date',
        'next_renewal_at'     => 'date',
        'monthly_cost'        => 'decimal:2',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(InfraServer::class, 'server_id');
    }

    public function getDaysUntilRenewalAttribute(): ?int
    {
        return $this->next_renewal_at ? now()->diffInDays($this->next_renewal_at, false) : null;
    }
}
