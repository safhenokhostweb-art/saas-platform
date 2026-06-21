<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteLifecycleInvoice;
use App\Services\Lifecycle\SiteLifecycleService;
use App\Services\Lifecycle\SiteLifecycleNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SiteLifecycleController extends Controller
{
    public function __construct(
        private SiteLifecycleService $lifecycle,
        private SiteLifecycleNotificationService $notifications,
    ) {}

    /** Dashboard: lifecycle overview for a specific site */
    public function index(Site $site)
    {
        $logs     = $site->lifecycleLogs()->latest()->paginate(20);
        $invoices = $site->lifecycleInvoices()->latest()->get();

        return view('admin.lifecycle.index', compact('site', 'logs', 'invoices'));
    }

    /** Platform-wide lifecycle overview */
    public function overview()
    {
        $summary = [
            'active'       => Site::where('lifecycle_status', 'active')->count(),
            'grace_period' => Site::where('lifecycle_status', 'grace_period')->count(),
            'suspended'    => Site::where('lifecycle_status', 'suspended')->count(),
            'archived'     => Site::where('lifecycle_status', 'archived')->count(),
            'deleted'      => Site::where('lifecycle_status', 'deleted')->count(),
        ];

        $gracePeriodSites = Site::where('lifecycle_status', 'grace_period')
            ->with('customer')
            ->orderBy('grace_period_ends_at')
            ->get();

        $suspendedSites = Site::where('lifecycle_status', 'suspended')
            ->with('customer')
            ->orderBy('suspended_at')
            ->get();

        $archivedSites = Site::where('lifecycle_status', 'archived')
            ->with('customer')
            ->orderByDesc('archived_at')
            ->get();

        return view('admin.lifecycle.overview', compact('summary', 'gracePeriodSites', 'suspendedSites', 'archivedSites'));
    }

    /** Force a manual state transition */
    public function forceTransition(Request $request, Site $site)
    {
        $request->validate([
            'to_status' => ['required', 'in:active,grace_period,suspended,archived,deleted'],
            'reason'    => ['required', 'string', 'max:500'],
        ]);

        $toStatus = $request->input('to_status');
        $reason   = $request->input('reason');
        $adminId  = Auth::id();

        match ($toStatus) {
            'active'       => $this->lifecycle->activate($site, $reason, $adminId),
            'grace_period' => $this->lifecycle->startGracePeriod($site, $reason, $adminId),
            'suspended'    => $this->lifecycle->suspend($site, $reason, $adminId),
            'archived'     => $this->lifecycle->archive($site, $reason, $adminId),
            'deleted'      => $this->lifecycle->delete($site, $reason, $adminId),
        };

        return redirect()
            ->route('admin.sites.lifecycle.index', $site)
            ->with('success', "وضعیت سایت به «{$site->lifecycleStatusLabel()}» تغییر کرد.");
    }

    /** Force restore from archived state */
    public function forceRestore(Request $request, Site $site)
    {
        $this->lifecycle->restore($site, 'بازگردانی دستی توسط ادمین', Auth::id());

        return redirect()
            ->route('admin.sites.lifecycle.index', $site)
            ->with('success', 'سایت با موفقیت بازگردانی شد.');
    }

    /**
     * C01 — Create restore invoice + send payment link SMS to customer.
     * Admin action: POST /admin/sites/{site}/lifecycle/send-payment-link
     */
    public function sendPaymentLink(Request $request, Site $site)
    {
        $invoice = $this->lifecycle->createRestoreInvoice($site);

        $paymentUrl = 'https://' . $site->domain . '/lifecycle-pay/' . $invoice->invoice_number;

        $this->notifications->sendRestoreInvoiceSms($site, $invoice->invoice_number, $invoice->amount);

        return redirect()
            ->route('admin.sites.lifecycle.index', $site)
            ->with('success', "لینک پرداخت ارسال شد: {$paymentUrl}");
    }

    /** Mark a lifecycle invoice as paid */
    public function markInvoicePaid(Request $request, Site $site, SiteLifecycleInvoice $invoice)
    {
        $request->validate([
            'payment_reference' => ['required', 'string', 'max:200'],
        ]);

        $this->lifecycle->markInvoicePaid($invoice, $request->input('payment_reference'));

        return redirect()
            ->route('admin.sites.lifecycle.index', $site)
            ->with('success', 'فاکتور به‌عنوان پرداخت‌شده ثبت شد.');
    }

    /** Lifecycle settings page */
    public function settings()
    {
        $settings = [
            'grace_period_days'     => config('lifecycle.grace_period_days', 15),
            'archive_after_days'    => config('lifecycle.archive_after_days', 90),
            'delete_after_days'     => config('lifecycle.delete_after_days', 365),
            'reactivation_fee'      => config('lifecycle.reactivation_fee', 0),
            'archive_restore_fee'   => config('lifecycle.archive_restore_fee', 0),
            'storage_retention_fee' => config('lifecycle.storage_retention_fee', 0),
        ];

        return view('admin.lifecycle.settings', compact('settings'));
    }

    /** Update lifecycle settings in .env via platform settings pattern */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'grace_period_days'     => ['required', 'integer', 'min:1', 'max:365'],
            'archive_after_days'    => ['required', 'integer', 'min:1', 'max:730'],
            'delete_after_days'     => ['required', 'integer', 'min:1', 'max:3650'],
            'reactivation_fee'      => ['required', 'integer', 'min:0'],
            'archive_restore_fee'   => ['required', 'integer', 'min:0'],
            'storage_retention_fee' => ['required', 'integer', 'min:0'],
        ]);

        $this->setEnvValue('LIFECYCLE_GRACE_PERIOD_DAYS',     $request->input('grace_period_days'));
        $this->setEnvValue('LIFECYCLE_ARCHIVE_AFTER_DAYS',    $request->input('archive_after_days'));
        $this->setEnvValue('LIFECYCLE_DELETE_AFTER_DAYS',     $request->input('delete_after_days'));
        $this->setEnvValue('LIFECYCLE_REACTIVATION_FEE',      $request->input('reactivation_fee'));
        $this->setEnvValue('LIFECYCLE_ARCHIVE_RESTORE_FEE',   $request->input('archive_restore_fee'));
        $this->setEnvValue('LIFECYCLE_STORAGE_RETENTION_FEE', $request->input('storage_retention_fee'));

        return redirect()
            ->route('admin.lifecycle.settings')
            ->with('success', 'تنظیمات چرخه عمر به‌روز شد.');
    }

    private function setEnvValue(string $key, mixed $value): void
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            return;
        }

        $env = file_get_contents($envPath);

        if (str_contains($env, "{$key}=")) {
            $env = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $env);
        } else {
            $env .= "\n{$key}={$value}";
        }

        file_put_contents($envPath, $env);
    }
}
