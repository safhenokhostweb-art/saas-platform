<?php

namespace SaaSPlatform\Modules\Forms\Contracts;

use SaaSPlatform\CoreUtils\Contracts\Platform\SiteModelInterface;
use SaaSPlatform\Modules\Forms\Models\Form;

interface FormSiteServiceInterface
{
    public function getEditData(Form $form): array;

    public function syncSites(Form $form, array $siteIds): void;

    public function toggleSite(Form $form, SiteModelInterface $site): bool;
}
