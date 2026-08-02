<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 18-E — Part 2: access_token/refresh_token use Laravel's built-in
 * 'encrypted' cast (APP_KEY-derived) — encrypted at rest, transparently
 * decrypted on read, never logged or serialized in plain text (Eloquent
 * excludes encrypted-cast attributes from array/JSON dumps unless
 * explicitly accessed).
 */
class SeoAdvancedSearchConsoleConnection extends Model
{
    protected $table = 'seo_advanced_search_console_connections';

    protected $fillable = [
        'site_id',
        'google_account_email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'property_url',
        'connected_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
