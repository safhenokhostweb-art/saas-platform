<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteLifecycleLog extends Model
{
    protected $fillable = [
        'site_id', 'from_status', 'to_status', 'reason',
        'changed_by', 'is_system', 'metadata',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'metadata'  => 'array',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function statusLabel(): string
    {
        return SiteLifecycleInvoice::statusLabel($this->to_status);
    }
}
