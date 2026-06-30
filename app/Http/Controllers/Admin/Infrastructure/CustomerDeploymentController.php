<?php

namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Jobs\CustomerInstallationPipelineJob;
use App\Jobs\CustomerInstallationRollbackJob;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationPackage;
use App\Models\CustomerRemoteServer;
use App\Models\Site;
use App\Services\CustomerInstallation\CustomerInstallationRollbackService;
use App\Services\CustomerInstallation\RemoteConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CustomerDeploymentController extends Controller
{
    public function dashboard()
    {
        $active   = CustomerInstallation::whereIn('status', ['pending', 'running', 'rolling_back'])->with('remoteServer')->latest()->get();
        $failed   = CustomerInstallation::whereIn('status', ['failed', 'rollback_failed'])->count();
        $complete = CustomerInstallation::where('status', 'complete')->count();
        $queued   = CustomerInstallation::where('status', 'pending')->count();
        $servers  = CustomerRemoteServer::count();
        $packages = CustomerInstallationPackage::where('is_active', true)->count();

        $recent = CustomerInstallation::with('remoteServer')
            ->latest()
            ->take(10)
            ->get();

        return view('admin.infrastructure.customer-deployment.dashboard', compact(
            'active', 'failed', 'complete', 'queued', 'servers', 'packages', 'recent'
        ));
    }

    public function wizard()
    {
        $servers  = CustomerRemoteServer::orderBy('label')->get();
        $packages = CustomerInstallationPackage::where('is_active', true)->latest()->get();

        return view('admin.infrastructure.customer-deployment.wizard', compact('servers', 'packages'));
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'customer_company'  => 'required|string|max:255',
            'owner_name'        => 'required|string|max:255',
            'customer_email'    => 'required|email|max:255',
            'phone'             => 'nullable|string|max:30',
            'language'          => 'required|in:fa,en,ar',
            'timezone'          => 'required|string|max:100',
            'currency'          => 'required|string|max:10',
            'customer_domain'   => 'required|string|max:255',
            'remote_server_id'  => 'required|exists:customer_remote_servers,id',
            'install_mode'      => 'required|in:MODE_FRESH,MODE_RESTORE,MODE_CLONE,MODE_MOVE,MODE_REINSTALL',
            'license_type'      => 'required|in:basic,professional,enterprise',
            'theme_slug'        => 'nullable|string|max:100',
            'modules'           => 'nullable|array',
            'backup_policy'     => 'nullable|in:none,daily,weekly',
            'ssl_enabled'       => 'nullable|boolean',
        ]);

        // DNS existence check — verify domain has at least one A/CNAME/AAAA record.
        // We do NOT check if it points to this server (ArvanCloud CDN is in front).
        $domainForDns = preg_replace('#^https?://#i', '', rtrim($validated['customer_domain'], '/'));
        try {
            $dnsExists = checkdnsrr($domainForDns, 'A')
                      || checkdnsrr($domainForDns, 'CNAME')
                      || checkdnsrr($domainForDns, 'AAAA');
        } catch (\Throwable) {
            $dnsExists = true; // fail-open: if DNS query itself fails, don't block the install
        }

        if (!$dnsExists) {
            return back()
                ->withInput()
                ->withErrors([
                    'customer_domain' => "دامنه «{$domainForDns}» در DNS یافت نشد. مطمئن شوید دامنه ثبت شده و رکورد DNS آن (A یا CNAME) فعال باشد.",
                ]);
        }

        // Map form field names to DTO field names expected by CustomerInstallRequest::fromArray()
        $payload = [
            'company'          => $validated['customer_company'],
            'owner_name'       => $validated['owner_name'],
            'email'            => $validated['customer_email'],
            'phone'            => $validated['phone'] ?? '',
            'language'         => $validated['language'],
            'timezone'         => $validated['timezone'],
            'currency'         => $validated['currency'],
            'domain'           => $validated['customer_domain'],
            'license_type'     => $validated['license_type'],
            'theme_slug'       => $validated['theme_slug'] ?? '',
            'modules'          => $validated['modules'] ?? [],
            'backup_policy'    => $validated['backup_policy'] ?? 'daily',
            'ssl_enabled'      => (bool) ($validated['ssl_enabled'] ?? false),
            'smtp'             => [],
            'remote_server_id' => (int) $validated['remote_server_id'],
            'install_mode'     => $validated['install_mode'],
        ];

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $validated['remote_server_id'],
            'installer_user_id' => Auth::id(),
            'install_mode'      => $validated['install_mode'],
            'status'            => 'pending',
            'customer_company'  => $validated['customer_company'],
            'customer_email'    => $validated['customer_email'],
            'customer_domain'   => $validated['customer_domain'],
            'request_payload'   => $payload,
        ]);

        CustomerInstallationPipelineJob::dispatch($installation->id)
            ->onConnection('customer-install')
            ->onQueue('customer-install');

        return redirect()
            ->route('admin.infrastructure.customer-deployment.installations.show', $installation)
            ->with('success', 'نصب با موفقیت در صف قرار گرفت.');
    }

    public function history(Request $request)
    {
        $query = CustomerInstallation::with('remoteServer')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('customer_company', 'like', "%{$term}%")
                  ->orWhere('customer_domain', 'like', "%{$term}%")
                  ->orWhere('customer_email', 'like', "%{$term}%");
            });
        }
        if ($request->filled('server_id')) {
            $query->where('remote_server_id', $request->server_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $installations = $query->paginate(25)->withQueryString();
        $servers = CustomerRemoteServer::orderBy('label')->get();

        return view('admin.infrastructure.customer-deployment.history', compact('installations', 'servers'));
    }

    public function show(CustomerInstallation $installation)
    {
        $installation->load(['remoteServer', 'logs', 'rollbacks']);

        $canRollback = in_array($installation->status, ['failed', 'rollback_failed'])
            && $installation->rollbacks()->whereNull('executed_at')->exists();

        return view('admin.infrastructure.customer-deployment.show', compact('installation', 'canRollback'));
    }

    public function rollback(Request $request, CustomerInstallation $installation)
    {
        if (!in_array($installation->status, ['failed', 'rollback_failed'])) {
            return back()->with('error', 'فقط نصب‌های ناموفق قابل بازگشت هستند.');
        }

        if (!$installation->rollbacks()->whereNull('executed_at')->exists()) {
            return back()->with('error', 'هیچ checkpoint بازگشتی برای این نصب وجود ندارد.');
        }

        CustomerInstallationRollbackJob::dispatch($installation->id)
            ->onConnection('customer-install')
            ->onQueue('customer-install');

        return redirect()
            ->route('admin.infrastructure.customer-deployment.installations.show', $installation)
            ->with('success', 'عملیات بازگشت در صف قرار گرفت.');
    }

    public function destroy(Request $request, CustomerInstallation $installation)
    {
        // Redirect to the Enterprise Delete Engine confirmation page.
        // The old direct-delete path has been replaced by the pipeline-based engine
        // (PHASE-003H) which is transactional, audited, and verifiable.
        return redirect()
            ->route('admin.infrastructure.customer-deployment.installations.delete.confirm', $installation)
            ->with('info', 'لطفاً فرآیند حذف را از طریق موتور حذف سازمانی تأیید کنید.');
    }

    public function packages()
    {
        $packages = CustomerInstallationPackage::latest()->paginate(20);
        return view('admin.infrastructure.customer-deployment.packages', compact('packages'));
    }
}
