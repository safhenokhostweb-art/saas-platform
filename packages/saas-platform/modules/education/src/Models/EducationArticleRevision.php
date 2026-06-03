<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EducationArticleRevision extends Model
{
    protected $table = 'edu_article_revisions';

    public $timestamps = false;

    protected $fillable = [
        'edu_article_id', 'author_id',
        'title', 'excerpt', 'body',
        'revision_number', 'change_summary',
    ];

    protected $casts = [
        'created_at'      => 'datetime',
        'revision_number' => 'integer',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(EducationArticle::class, 'edu_article_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'), 'author_id');
    }
}
