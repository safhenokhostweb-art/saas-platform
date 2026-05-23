<?php

namespace SaaSPlatform\Modules\Forms\Contracts;

use SaaSPlatform\Modules\Forms\Models\Form;
use App\Models\Site;

interface FormSiteServiceInterface
{
    public function getEditData(Form $form): array;

    public function syncSites(Form $form, array $siteIds): void;

    public function toggleSite(Form $form, Site $site): bool;
}
