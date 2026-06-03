<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EducationBookmark extends Model
{
    protected $table = 'edu_bookmarks';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'site_id',
        'bookmarkable_id', 'bookmarkable_type',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'));
    }
}
