<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Site;
use App\Models\Form;
use App\Models\Module as ModuleModel;
use App\Models\PlatformLicense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    private const EXPIRY_WARNING_DAYS = 30;

    public function index()
    {
        $customers = Customer::count();
        $sites     = Site::count();
        $forms     = Form::count();
        $modulesCount = ModuleModel::count();

        $activeLicensesCount = 0;
        if (Schema::hasTable('platform_licenses')) {
            $activeLicensesCount = PlatformLicense::whereNull('revoked_at')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count();
        }

        // Revenue stats (paid invoices this month)
        $revenueThisMonth = 0;
        $paidInvoicesCount = 0;
        if (Schema::hasTable('invoices')) {
            $revenueThisMonth = Invoice::where('status', Invoice::STATUS_PAID)
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount_irr');
            $paidInvoicesCount = Invoice::where('status', Invoice::STATUS_PAID)->count();
        }

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

        // Active expiry warnings based on CURRENT site dates, not historical logs
        $activeExpiryWarnings = $this->computeActiveExpiryWarnings();

        return view('admin.dashboard', compact(
            'customers',
            'sites',
            'forms',
            'modulesCount',
            'activeLicensesCount',
            'revenueThisMonth',
            'paidInvoicesCount',
            'formsList',
            'sitesList',
            'formSiteColumnExists',
            'sitesWithModules',
            'allModules',
            'activeExpiryWarnings'
        ));
    }

    private function computeActiveExpiryWarnings(): \Illuminate\Support\Collection
    {
        $warnings = collect();
        $window = self::EXPIRY_WARNING_DAYS;

        $fields = [
            'host'    => ['field' => 'host_expires_at',    'label' => 'هاست'],
            'support' => ['field' => 'support_expires_at', 'label' => 'پشتیبانی'],
            'domain'  => ['field' => 'domain_expires_at',  'label' => 'دامنه'],
        ];

        Site::where('is_active', true)->get()->each(function (Site $site) use (&$warnings, $fields, $window) {
            foreach ($fields as $type => $meta) {
                $date = $site->{$meta['field']};
                if (!$date) {
                    continue;
                }
                $exp = Carbon::parse($date);
                $daysLeft = (int) now()->diffInDays($exp, false);
                // Only show if not yet expired AND within warning window
                if ($daysLeft >= 0 && $daysLeft <= $window) {
                    $warnings->push((object) [
                        'site'       => $site,
                        'type'       => $type,
                        'label'      => $meta['label'],
                        'expires_at' => $exp,
                        'days_left'  => $daysLeft,
                    ]);
                }
            }
        });

        return $warnings->sortBy('days_left')->values();
    }
}
