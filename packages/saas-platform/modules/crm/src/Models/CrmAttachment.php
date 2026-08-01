<?php

namespace SaaSPlatform\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use SaaSPlatform\Modules\Crm\Models\Concerns\HasSiteTenantScope;

class CrmAttachment extends Model
{
    use HasSiteTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'crm_attachments';

    protected $fillable = [
        'site_id',
        'attachable_type',
        'attachable_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
