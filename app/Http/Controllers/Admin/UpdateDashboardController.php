<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Site;
use App\Models\SiteUpdate;
use App\Services\ModuleUpdateEligibilityService;
use Illuminate\Support\Facades\Schema;

/**
 * داشبورد مرکزی بروزرسانی‌ها.
 */
class UpdateDashboardController extends Controller
{
    public function __construct(
        protected ModuleUpdateEligibilityService $updateEligibility
    ) {}

    public function index()
    {
        $sites = Site::with('customer')->orderBy('title')->get();
        $allModules = Schema::hasTable('modules') ? Module::orderBy('slug')->get() : collect();

        $siteStatuses = [];
        foreach ($sites as $site) {
            $eligible = $this->updateEligibility->isEligibleForModuleUpdates($site);
            $availableUpdates = $eligible
                ? $this->updateEligibility->getAvailableModuleUpdates($site)
                : [];
            $siteStatuses[$site->id] = [
                'eligible' => $eligible,
                'updates' => $availableUpdates,
            ];
        }

        $recentSiteUpdates = SiteUpdate::with('site')
            ->latest('distributed_at')
            ->take(15)
            ->get();

        return view('admin.updates.index', compact(
            'sites',
            'allModules',
            'siteStatuses',
            'recentSiteUpdates'
        ));
    }
}
