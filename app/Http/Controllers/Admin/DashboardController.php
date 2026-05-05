<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Site;
use App\Models\Form;
use App\Models\Module as ModuleModel;
use App\Models\ExpiryReminderLog;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        $customers = Customer::count();
        $sites     = Site::count();
        $forms     = Form::count();

        $formsList = collect();
        $sitesList = collect();
        $formSiteColumnExists = Schema::hasColumn('form_site', 'is_active');

        if ($formSiteColumnExists) {
            $formsList = Form::with(['sites' => fn ($q) => $q->withPivot('is_active')])->latest()->get();
            $sitesList = Site::orderBy('title')->get();
        }

        $sitesWithModules = collect();
        $allModules = collect();
        if (Schema::hasTable('site_modules') && Schema::hasTable('modules')) {
            $allModules = ModuleModel::orderBy('slug')->get();
            $sitesWithModules = Site::with(['modules' => fn ($q) => $q->wherePivot('is_active', true)])
                ->orderBy('title')
                ->get();
        }

        $expiryReminders = collect();
        if (Schema::hasTable('expiry_reminder_logs')) {
            $expiryReminders = ExpiryReminderLog::with('site')
                ->orderByDesc('sent_at')
                ->limit(15)
                ->get();
        }

        return view('admin.dashboard', compact(
            'customers',
            'sites',
            'forms',
            'formsList',
            'sitesList',
            'formSiteColumnExists',
            'sitesWithModules',
            'allModules',
            'expiryReminders'
        ));
    }
}
