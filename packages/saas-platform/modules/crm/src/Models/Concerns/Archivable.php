<?php

namespace SaaSPlatform\Modules\Crm\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 19 — Part 4: `archived_at` is a distinct state from soft-delete
 * (`deleted_at`) — archiving hides a record from default working lists
 * without removing it or counting as a deletion; soft-delete remains the
 * separate "trash" mechanism. Shared by Company/Contact/Lead/Opportunity.
 */
trait Archivable
{
    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
    }

    public function unarchive(): void
    {
        $this->update(['archived_at' => null]);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }
}
