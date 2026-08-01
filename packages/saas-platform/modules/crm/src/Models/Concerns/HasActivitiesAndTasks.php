<?php

namespace SaaSPlatform\Modules\Crm\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use SaaSPlatform\Modules\Crm\Models\CrmActivity;
use SaaSPlatform\Modules\Crm\Models\CrmAttachment;
use SaaSPlatform\Modules\Crm\Models\CrmTask;

/**
 * Phase 19 — Part 6/7: shared by every entity that appears in a Customer
 * Profile timeline/file list (Company, Contact, Lead, Opportunity).
 */
trait HasActivitiesAndTasks
{
    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subject')->latest('occurred_at');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(CrmTask::class, 'subject')->orderBy('due_at');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(CrmAttachment::class, 'attachable')->latest();
    }
}
