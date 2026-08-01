<?php

namespace SaaSPlatform\Modules\Crm\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use SaaSPlatform\Modules\Crm\Models\CrmTag;

/**
 * Phase 19 — Part 4: shared by every taggable CRM entity (Company,
 * Contact, Lead, Opportunity) via the crm_taggables polymorphic pivot.
 */
trait Taggable
{
    public function tags(): MorphToMany
    {
        // crm_taggables has only created_at (no updated_at — a tag
        // attachment doesn't change once created), so withTimestamps()
        // is deliberately NOT called here; sync()/attach() insert without
        // touching a timestamp column that doesn't exist. The pivot's
        // foreign key is `tag_id` (not Eloquent's default `crm_tag_id`
        // guess), so it must be passed explicitly.
        return $this->morphToMany(CrmTag::class, 'taggable', 'crm_taggables', 'taggable_id', 'tag_id');
    }

    public function syncTags(array $tagIds): void
    {
        $this->tags()->sync($tagIds);
    }
}
