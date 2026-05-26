<?php

namespace SaaSPlatform\Modules\Forms\Services;

use SaaSPlatform\CoreUtils\Contracts\Platform\SiteModelInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteRepositoryInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormSiteServiceInterface;
use SaaSPlatform\Modules\Forms\Models\Form;

class FormSiteService implements FormSiteServiceInterface
{
    public function __construct(
        protected SiteRepositoryInterface $siteRepository
    ) {}

    public function getEditData(Form $form): array
    {
        $sites    = $this->siteRepository->allForDisplay();
        $attached = $form->sites()->wherePivot('is_active', true)->pluck('sites.id')->toArray();

        return compact('form', 'sites', 'attached');
    }

    public function syncSites(Form $form, array $siteIds): void
    {
        $allSiteIds = $this->siteRepository->allIds();
        $sync = $allSiteIds->mapWithKeys(
            fn ($id) => [$id => ['is_active' => in_array($id, $siteIds)]]
        )->toArray();
        $form->sites()->sync($sync);
    }

    public function toggleSite(Form $form, SiteModelInterface $site): bool
    {
        $current  = $form->sites()->where('site_id', $site->getId())->first();
        $isActive = $current ? !($current->pivot->is_active ?? false) : true;
        $form->sites()->syncWithoutDetaching([$site->getId() => ['is_active' => $isActive]]);

        return $isActive;
    }
}
