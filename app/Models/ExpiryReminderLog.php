<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpiryReminderLog extends Model
{
    public const TYPE_SUPPORT = 'support';
    public const TYPE_HOST = 'host';
    public const TYPE_DOMAIN = 'domain';

    protected $fillable = ['site_id', 'type', 'title', 'body', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
