<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Site;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Crm\Http\Controllers\Concerns\ResolvesCurrentSite;
use SaaSPlatform\Modules\Crm\Services\CrmDashboardService;

/**
 * Phase 20 — Part 2: customer-facing dashboard. Takes NO site_id route
 * parameter — resolves it from the authenticated user's own site (see
 * ResolvesCurrentSite) — so there is no URL a customer could edit to see
 * another site's CRM data.
 */
class CrmCustomerDashboardController extends Controller
{
    use ResolvesCurrentSite;

    public function show(Request $request, CrmDashboardService $service)
    {
        $siteId = $this->currentSiteId($request);
        abort_unless($siteId !== null, 403, 'هیچ سایتی به این کاربر متصل نیست.');

        $data = $service->build($siteId);

        return view('crm::site.dashboard', $data);
    }
}
