<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Crm\Services\CrmDashboardService;

/**
 * Phase 20 — Part 2: takes a plain int $siteId route parameter (never a
 * host Eloquent Site model binding), matching every other CRM admin
 * controller's convention. An admin can view any site's dashboard by
 * navigating to a different {siteId} — the platform's own admin/auth
 * middleware is what actually restricts which sites an admin may reach
 * (unchanged, generic, outside this module's concern).
 */
class CrmDashboardController extends Controller
{
    public function show(int $siteId, CrmDashboardService $service)
    {
        $data = $service->build($siteId);

        return view('crm::admin.dashboard.show', array_merge(['siteId' => $siteId], $data));
    }
}
