<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;

class EducationSyncLog extends Model
{
    protected $table = 'edu_sync_logs';

    protected $fillable = [
        'site_id', 'sync_type',
        'articles_synced', 'walkthroughs_synced', 'learning_paths_synced',
        'articles_archived', 'articles_skipped',
        'status', 'summary', 'duration_ms', 'ran_at',
    ];

    protected $casts = [
        'summary'              => 'array',
        'ran_at'               => 'datetime',
        'articles_synced'      => 'integer',
        'walkthroughs_synced'  => 'integer',
        'learning_paths_synced'=> 'integer',
        'articles_archived'    => 'integer',
        'articles_skipped'     => 'integer',
        'duration_ms'          => 'integer',
    ];

    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('ran_at');
    }
}
