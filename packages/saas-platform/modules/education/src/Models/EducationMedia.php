<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EducationMedia extends Model
{
    protected $table = 'edu_media';

    protected $fillable = [
        'edu_article_id', 'edu_walkthrough_step_id',
        'type', 'video_provider', 'video_path',
        'thumbnail', 'duration', 'alt_text',
        'sort_order', 'auto_generated', 'meta',
    ];

    protected $casts = [
        'auto_generated' => 'boolean',
        'duration'       => 'integer',
        'sort_order'     => 'integer',
        'meta'           => 'array',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(EducationArticle::class, 'edu_article_id');
    }

    public function walkthroughStep(): BelongsTo
    {
        return $this->belongsTo(EducationWalkthroughStep::class, 'edu_walkthrough_step_id');
    }

    public function getEmbedUrlAttribute(): ?string
    {
        return match ($this->video_provider) {
            'youtube' => $this->video_path ? "https://www.youtube.com/embed/{$this->video_path}" : null,
            'aparat'  => $this->video_path ? "https://www.aparat.com/video/video/embed/videohash/{$this->video_path}/vt/frame" : null,
            default   => $this->video_path,
        };
    }
}
