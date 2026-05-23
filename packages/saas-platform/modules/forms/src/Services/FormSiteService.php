<?php

namespace SaaSPlatform\Modules\Forms\Services;

use SaaSPlatform\Modules\Forms\Contracts\FormSiteServiceInterface;
use SaaSPlatform\Modules\Forms\Models\Form;
use App\Models\Site;

class FormSiteService implements FormSiteServiceInterface
{
    public function getEditData(Form $form): array
    {
        $sites = Site::latest()->get();
        $attached = $form->sites()->wherePivot('is_active', true)->pluck('sites.id')->toArray();

        return compact('form', 'sites', 'attached');
    }

    public function syncSites(Form $form, array $siteIds): void
    {
        $allSiteIds = Site::pluck('id');
        $sync = $allSiteIds->mapWithKeys(fn ($id) => [$id => ['is_active' => in_array($id, $siteIds)]])->toArray();
        $form->sites()->sync($sync);
    }

    public function toggleSite(Form $form, Site $site): bool
    {
        $current = $form->sites()->where('site_id', $site->id)->first();
        $isActive = $current ? !($current->pivot->is_active ?? false) : true;
        $form->sites()->syncWithoutDetaching([$site->id => ['is_active' => $isActive]]);

        return $isActive;
    }
}
