<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'first_name',
        'last_name',
        'postal_code',
        'address',
        'mobile',
        'lat',
        'lng',
        'map_link',
        'map_address',
        'avatar',
        'extra',
        'is_complete',
    ];

    protected $casts = [
        'extra' => 'array',   // برای فیلدهای داینامیک پروفایل
        'is_complete' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Resolve the avatar's public URL through MediaStorage instead of the raw
     * url() helper, so it keeps working once avatars move off the local
     * 'uploads' disk. Preserves the legacy "uploads/..." stored-path
     * convention and passes already-absolute URLs (e.g. http...) through
     * unchanged.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http')) {
            return $this->avatar;
        }

        return app(\App\Services\Infrastructure\MediaStorage::class)->avatarUrl($this->avatar);
    }
}
