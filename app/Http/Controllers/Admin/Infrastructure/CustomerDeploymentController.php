<?php

namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Jobs\CustomerInstallationPipelineJob;
use App\Jobs\CustomerInstallationRollbackJob;
use App\Models\Customer;
use App\Models\CustomerDeletion;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationPackage;
use App\Models\CustomerRemoteServer;
use App\Models\InstallationCredential;
use App\Models\Site;
use App\Services\ActivityLogger;
use App\Services\AuthorizationService;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\CustomerInstallationRollbackService;
use App\Services\CustomerInstallation\CustomerInstallationOrchestrator;
use App\Services\CustomerInstallation\InstallationTargetGuard;
use App\Services\Onboarding\SiteTemplateBinder;
use App\Services\CustomerInstallation\RemoteConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerDeploymentController extends Controller
{
    public function __construct(
        protected AuthorizationService $authorizationService,
        protected CredentialVault $credentialVault,
    ) {}

    /**
     * Phase 13-O8 — second authorization layer for manual rollback, on top
     * of (never a replacement for) the existing ['auth','admin'] middleware,
     * mirroring authorizeUpgrade() in SiteModuleUpgradeController (13-M5).
     * AuthorizationService::can() already short-circuits true for
     * super_admin (User::isSuperAdmin()), so no separate super_admin
     * branch is needed here.
     */
    private function authorizeRollback(): void
    {
        if (!$this->authorizationService->can(Auth::user(), 'tenant.rollback.execute')) {
            abort(403, 'شما اجازهٔ اجرای عملیات بازگشت (Rollback) را ندارید.');
        }
    }

    /**
     * Phase 13-O9e — permission boundary for viewing a decrypted installation
     * credential, mirroring authorizeRollback() (13-O8) / authorizeUpgrade()
     * (13-M5). AuthorizationService::can() already short-circuits true for
     * super_admin, so no separate super_admin branch is needed here.
     */
    private function authorizeCredentialView(): void
    {
        if (!$this->authorizationService->can(Auth::user(), 'tenant.credentials.view')) {
            abort(403, 'شما اجازهٔ مشاهدهٔ اطلاعات ورود مشتری را ندارید.');
        }
    }

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
        // ADR-088 — onboarding is one wizard now; this page only forwards to it.
        return redirect()->route('admin.onboarding.create', [], 301);
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

        $guard = app(InstallationTargetGuard::class);

        $driverError = $guard->driverError(CustomerRemoteServer::findOrFail($validated['remote_server_id']));
        if ($driverError !== null) {
            return back()->withInput()->withErrors(['remote_server_id' => $driverError]);
        }

        // DNS existence check — only for custom domains that the admin must have pre-configured.
        $dnsError = $guard->customDomainDnsError($validated['customer_domain']);
        if ($dnsError !== null) {
            return back()->withInput()->withErrors(['customer_domain' => $dnsError]);
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

        // Phase 13-O9e — show() no longer decrypts or exposes the credential
        // value; it only tells the view whether a reveal button should be
        // offered at all. The actual password is only ever decrypted inside
        // revealCredential(), behind its own permission check and audit log.
        $hasCredential = $this->hasCredential($installation);

        // ADR-088: the progress list is the pipeline's own stage list for
        // this mode (it used to be a hardcoded copy that had drifted).
        $stageLabels = [];
        foreach (app(CustomerInstallationOrchestrator::class)->buildStageList((string) $installation->install_mode) as $stage) {
            $stageLabels[$stage->name()] = $stage->label();
        }
        $templateBinding = app(SiteTemplateBinder::class)->binding($installation);

        return view('admin.infrastructure.customer-deployment.show', compact('installation', 'canRollback', 'hasCredential', 'stageLabels', 'templateBinding'));
    }

    /**
     * Phase 13-O9a — new installations store admin_password encrypted via
     * CredentialVault (InstallationCredential), referenced from
     * metadata.credential_ids.admin_password. Installations created before
     * this phase still have it as plaintext directly under
     * metadata.admin_password — that fallback is intentional (no backfill
     * or deletion of old data in this phase).
     *
     * Phase 13-O9e — this bool-only check (no decrypt) backs the "show a
     * reveal button?" decision on the detail page; boolean existence check
     * only, must never call CredentialVault::decrypt().
     */
    private function hasCredential(CustomerInstallation $installation): bool
    {
        $credentialId = $installation->metadata['credential_ids']['admin_password'] ?? null;

        if ($credentialId) {
            return InstallationCredential::where('id', $credentialId)
                ->where('installation_id', $installation->id)
                ->where('type', 'admin_password')
                ->exists();
        }

        return !empty($installation->metadata['admin_password'] ?? null);
    }

    /**
     * Phase 13-O9e — Secure Reveal endpoint (Phase 13-O9d, Option B).
     * Decrypts and returns the installation's admin credential only on an
     * explicit, permission-gated, audited request — never as a side effect
     * of loading show().
     */
    private function resolveAdminPassword(CustomerInstallation $installation): ?string
    {
        $credentialId = $installation->metadata['credential_ids']['admin_password'] ?? null;

        if ($credentialId) {
            $credential = InstallationCredential::where('id', $credentialId)
                ->where('installation_id', $installation->id)
                ->where('type', 'admin_password')
                ->first();

            if ($credential) {
                try {
                    return $this->credentialVault->decrypt($credential->encrypted_value);
                } catch (\Throwable $e) {
                    Log::error('CDE: Failed to decrypt admin_password credential', [
                        'installation_id' => $installation->id,
                        'credential_id'   => $credential->id,
                        'error'           => $e->getMessage(),
                    ]);
                    return null;
                }
            }
        }

        return $installation->metadata['admin_password'] ?? null;
    }

    public function revealCredential(CustomerInstallation $installation)
    {
        $this->authorizeCredentialView();

        $password = $this->resolveAdminPassword($installation);

        if ($password === null) {
            return response()->json(['message' => 'رمزی برای این نصب ثبت نشده است.'], 404);
        }

        ActivityLogger::log(
            'installation.credential_viewed',
            "مشاهده رمز عبور ادمین برای نصب {$installation->customer_domain}",
            $installation,
            [
                'installation_id' => $installation->id,
                'site_id'         => $installation->site_id,
                'credential_type' => 'admin_password',
                'triggered_by'    => Auth::id(),
            ]
        );

        return response()->json(['password' => $password]);
    }

    public function rollback(Request $request, CustomerInstallation $installation)
    {
        $this->authorizeRollback();

        if (!in_array($installation->status, ['failed', 'rollback_failed'])) {
            return back()->with('error', 'فقط نصب‌های ناموفق قابل بازگشت هستند.');
        }

        if (!$installation->rollbacks()->whereNull('executed_at')->exists()) {
            return back()->with('error', 'هیچ checkpoint بازگشتی برای این نصب وجود ندارد.');
        }

        CustomerInstallationRollbackJob::dispatch($installation->id)
            ->onConnection('customer-install')
            ->onQueue('customer-install');

        ActivityLogger::log(
            'installation.rollback_requested',
            "درخواست بازگشت (Rollback) برای نصب {$installation->customer_domain}",
            $installation,
            [
                'installation_id' => $installation->id,
                'site_id'         => $installation->site_id,
                'triggered_by'    => Auth::id(),
                'reason'          => $request->input('reason'),
            ]
        );

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

    /**
     * Manual / force delete — bypasses the Enterprise Delete Engine (SSH pipeline).
     *
     * Scoped to installations that never reached a live, fully-provisioned customer
     * (rolled_back / failed / rollback_failed). These installs either have no remote
     * resources left to clean up (rollback already did that) or the remote resources
     * were never fully created, so the SSH-based pipeline has nothing valid to act on
     * and gets stuck. This purges the platform-side DB records directly.
     */
    public function forceDestroy(CustomerInstallation $installation)
    {
        if (!in_array($installation->status, ['rolled_back', 'failed', 'rollback_failed'])) {
            return back()->with('error', 'حذف دستی فقط برای نصب‌های ناموفق یا بازگشت‌خورده مجاز است.');
        }

        DB::transaction(function () use ($installation) {
            $siteId = $installation->site_id;

            if ($siteId) {
                $site = Site::find($siteId);
                if ($site) {
                    $customerId = $site->customer_id;
                    $site->delete();

                    if ($customerId && Site::where('customer_id', $customerId)->count() === 0) {
                        Customer::find($customerId)?->delete();
                    }
                }
            }

            foreach (CustomerDeletion::where('installation_id', $installation->id)->get() as $deletion) {
                $deletion->logs()->delete();
                $deletion->delete();
            }

            $installation->rollbacks()->delete();
            $installation->logs()->delete();
            $installation->delete();
        });

        Log::warning('CDE: Installation force-deleted manually (bypassed SSH pipeline)', [
            'installation_id' => $installation->id,
            'domain'          => $installation->customer_domain,
            'by_user'         => Auth::id(),
        ]);

        return redirect()
            ->route('admin.infrastructure.customer-deployment.history')
            ->with('success', "نصب «{$installation->customer_domain}» به‌صورت دستی حذف شد.");
    }

    public function packages()
    {
        $packages = CustomerInstallationPackage::latest()->paginate(20);
        return view('admin.infrastructure.customer-deployment.packages', compact('packages'));
    }
}
