<?php

namespace SaaSPlatform\Modules\Crm\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 19 — Part 4: shared by Company/Contact/Lead/Opportunity.
 */
trait Favoritable
{
    public function toggleFavorite(): bool
    {
        $this->update(['is_favorite' => !$this->is_favorite]);

        return $this->is_favorite;
    }

    public function scopeFavorites(Builder $query): Builder
    {
        return $query->where('is_favorite', true);
    }
}
