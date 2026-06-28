<?php

namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\CustomerRemoteServer;
use App\Models\SshKey;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\SshKeyManager;
use App\Services\CustomerInstallation\ApiTokenManager;
use Illuminate\Http\Request;

class AuthenticationCenterController extends Controller
{
    public function __construct(
        private readonly CredentialVault  $vault,
        private readonly SshKeyManager    $sshKeyManager,
        private readonly ApiTokenManager  $apiTokenManager,
    ) {}

    // ─── Overview ─────────────────────────────────────────────────────────────

    public function index()
    {
        $sshKeyCount     = SshKey::where('status', 'active')->count();
        $apiTokenCount   = CustomerRemoteServer::whereNotNull('api_token_encrypted')->count();
        $passwordOnly    = CustomerRemoteServer::where('auth_method', 'password')->count();
        $fingerprintOk   = CustomerRemoteServer::whereNotNull('key_fingerprint')->count();
        $totalTargets    = CustomerRemoteServer::count();
        $sshActiveKeys   = SshKey::where('status', 'active')->with('deploymentTarget')->latest()->take(5)->get();
        $recentKeys      = SshKey::latest()->take(10)->get();

        $securityScore = $this->computeSecurityScore();

        return view('admin.infrastructure.auth-center.index', compact(
            'sshKeyCount', 'apiTokenCount', 'passwordOnly',
            'fingerprintOk', 'totalTargets', 'sshActiveKeys',
            'recentKeys', 'securityScore'
        ));
    }

    // ─── SSH Keys ─────────────────────────────────────────────────────────────

    public function sshKeys()
    {
        $keys    = SshKey::with('deploymentTarget')->latest()->paginate(20);
        $servers = CustomerRemoteServer::orderBy('label')->get();
        return view('admin.infrastructure.auth-center.ssh-keys.index', compact('keys', 'servers'));
    }

    public function generateKeyForm()
    {
        $servers = CustomerRemoteServer::orderBy('label')->get();
        return view('admin.infrastructure.auth-center.ssh-keys.generate', compact('servers'));
    }

    public function generateKey(Request $request)
    {
        $validated = $request->validate([
            'label'      => 'required|string|max:100',
            'key_type'   => 'required|in:ed25519,rsa4096',
            'target_id'  => 'nullable|exists:customer_remote_servers,id',
            'comment'    => 'nullable|string|max:100',
        ]);

        $comment  = $validated['comment'] ?? null;
        $targetId = $validated['target_id'] ?? null;

        $key = $validated['key_type'] === 'ed25519'
            ? $this->sshKeyManager->generateEd25519($validated['label'], $comment, $targetId)
            : $this->sshKeyManager->generateRsa4096($validated['label'], $comment, $targetId);

        return redirect()
            ->route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.show', $key)
            ->with('success', "کلید SSH {$key->keyTypeLabel()} با موفقیت تولید شد.");
    }

    public function showKey(SshKey $key)
    {
        $servers = CustomerRemoteServer::where(function ($q) use ($key) {
            $q->where('type', 'ssh')->orWhere('type', 'docker');
        })->orderBy('label')->get();
        return view('admin.infrastructure.auth-center.ssh-keys.show', compact('key', 'servers'));
    }

    public function importKeyForm()
    {
        $servers = CustomerRemoteServer::orderBy('label')->get();
        return view('admin.infrastructure.auth-center.ssh-keys.import', compact('servers'));
    }

    public function importKey(Request $request)
    {
        $validated = $request->validate([
            'label'       => 'required|string|max:100',
            'private_key' => 'required|string',
            'target_id'   => 'nullable|exists:customer_remote_servers,id',
            'comment'     => 'nullable|string|max:100',
        ]);

        if (!$this->sshKeyManager->validatePrivateKey($validated['private_key'])) {
            return back()->withErrors(['private_key' => 'کلید خصوصی معتبر نیست.'])->withInput();
        }

        $key = $this->sshKeyManager->import(
            $validated['label'],
            $validated['private_key'],
            $validated['comment'] ?? null,
            $validated['target_id'] ?? null
        );

        return redirect()
            ->route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.show', $key)
            ->with('success', 'کلید SSH با موفقیت وارد شد.');
    }

    public function installKey(Request $request, SshKey $key)
    {
        $validated = $request->validate([
            'server_id' => 'required|exists:customer_remote_servers,id',
        ]);

        $server = CustomerRemoteServer::findOrFail($validated['server_id']);

        try {
            $this->sshKeyManager->installOnTarget($key, $server);

            return response()->json([
                'success'      => true,
                'message'      => 'کلید عمومی با موفقیت روی سرور نصب شد.',
                'installed_at' => $key->fresh()->installed_at?->format('Y-m-d H:i'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function testKey(Request $request, SshKey $key)
    {
        $validated = $request->validate([
            'server_id' => 'required|exists:customer_remote_servers,id',
        ]);

        $server = CustomerRemoteServer::findOrFail($validated['server_id']);

        try {
            $success = $this->sshKeyManager->testKey($key, $server);

            return response()->json([
                'success' => $success,
                'message' => $success ? 'اتصال با کلید موفق بود.' : 'احراز هویت با کلید ناموفق بود.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function activateKey(Request $request, SshKey $key)
    {
        $validated = $request->validate([
            'server_id' => 'required|exists:customer_remote_servers,id',
        ]);

        $server = CustomerRemoteServer::findOrFail($validated['server_id']);

        try {
            $this->sshKeyManager->activateOnServer($key, $server);
            return back()->with('success', 'کلید SSH روی مقصد فعال شد. احراز هویت به کلید تغییر کرد.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function rotateKey(Request $request, SshKey $key)
    {
        $server = $key->deploymentTarget;
        if (!$server) {
            return back()->with('error', 'این کلید به مقصدی متصل نیست.');
        }

        try {
            $newKey = $this->sshKeyManager->rotateKey($key, $server);
            return redirect()
                ->route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.show', $newKey)
                ->with('success', 'کلید SSH با موفقیت rotate شد. کلید قدیمی غیرفعال شد.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function revokeKey(Request $request, SshKey $key)
    {
        $server = $key->deploymentTarget;

        try {
            $this->sshKeyManager->revokeKey($key, $server);
            return back()->with('success', 'کلید SSH ابطال شد.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function deleteKey(SshKey $key)
    {
        if ($key->status === 'active') {
            return back()->with('error', 'ابتدا کلید را revoke کنید.');
        }
        $key->delete();
        return redirect()
            ->route('admin.infrastructure.customer-deployment.auth-center.ssh-keys')
            ->with('success', 'کلید SSH حذف شد.');
    }

    // ─── API Tokens ───────────────────────────────────────────────────────────

    public function apiTokens()
    {
        $servers = CustomerRemoteServer::whereIn('type', ['cpanel', 'directadmin'])
            ->orderBy('label')
            ->get()
            ->map(function ($server) {
                $server->token_has     = $this->apiTokenManager->hasToken($server);
                $server->token_masked  = $this->apiTokenManager->maskedPreview($server);
                $server->token_health  = $this->apiTokenManager->tokenHealth($server);
                return $server;
            });

        return view('admin.infrastructure.auth-center.api-tokens', compact('servers'));
    }

    public function storeApiToken(Request $request, CustomerRemoteServer $server)
    {
        $validated = $request->validate([
            'api_token' => 'required|string|min:8|max:500',
        ]);

        $this->apiTokenManager->store($server, $validated['api_token']);

        return response()->json(['success' => true, 'message' => 'توکن با موفقیت ذخیره شد.']);
    }

    public function rotateApiToken(Request $request, CustomerRemoteServer $server)
    {
        $validated = $request->validate([
            'api_token' => 'required|string|min:8|max:500',
        ]);

        $this->apiTokenManager->rotate($server, $validated['api_token']);

        return response()->json(['success' => true, 'message' => 'توکن با موفقیت rotate شد.']);
    }

    public function deleteApiToken(CustomerRemoteServer $server)
    {
        $this->apiTokenManager->delete($server);
        return back()->with('success', 'توکن API حذف شد.');
    }

    // ─── Security Audit ───────────────────────────────────────────────────────

    public function securityAudit()
    {
        $servers   = CustomerRemoteServer::orderBy('label')->get();
        $sshKeys   = SshKey::with('deploymentTarget')->get();
        $findings  = $this->runSecurityAudit($servers, $sshKeys);

        return view('admin.infrastructure.auth-center.security-audit', compact('servers', 'sshKeys', 'findings'));
    }

    // ─── Migration Wizard ─────────────────────────────────────────────────────

    public function migrationWizard(CustomerRemoteServer $server)
    {
        $existingKeys = SshKey::where('deployment_target_id', $server->id)
            ->orWhereNull('deployment_target_id')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.infrastructure.auth-center.migration-wizard', compact('server', 'existingKeys'));
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    private function computeSecurityScore(): int
    {
        $total   = CustomerRemoteServer::count();
        if ($total === 0) return 100;

        $keyAuth        = CustomerRemoteServer::where('auth_method', 'key')->count();
        $fingerprintSet = CustomerRemoteServer::whereNotNull('key_fingerprint')->count();
        $testedOk       = CustomerRemoteServer::where('last_test_result', 'success')->count();

        $score = (int) round(
            ($keyAuth / $total * 40) +
            ($fingerprintSet / $total * 30) +
            ($testedOk / $total * 30)
        );

        return $score;
    }

    private function runSecurityAudit($servers, $sshKeys): array
    {
        $findings = [];

        foreach ($servers as $server) {
            if ($server->auth_method === 'password') {
                $findings[] = [
                    'severity' => 'HIGH',
                    'server'   => $server->label,
                    'issue'    => 'احراز هویت با رمز عبور — توصیه: انتقال به کلید SSH',
                    'action'   => 'migration_wizard',
                    'target_id'=> $server->id,
                ];
            }

            if (in_array($server->type, ['ssh', 'docker']) && !$server->key_fingerprint) {
                $findings[] = [
                    'severity' => 'MEDIUM',
                    'server'   => $server->label,
                    'issue'    => 'Fingerprint هاست تأیید نشده — اولین اتصال محافظت ندارد',
                    'action'   => 'test_connection',
                    'target_id'=> $server->id,
                ];
            }

            if ($server->last_test_result === 'failure') {
                $findings[] = [
                    'severity' => 'HIGH',
                    'server'   => $server->label,
                    'issue'    => 'آخرین تست اتصال ناموفق بود',
                    'action'   => 'test_connection',
                    'target_id'=> $server->id,
                ];
            }

            if (!$server->last_tested_at || $server->last_tested_at->lt(now()->subDays(30))) {
                $findings[] = [
                    'severity' => 'LOW',
                    'server'   => $server->label,
                    'issue'    => 'بیش از ۳۰ روز تست نشده',
                    'action'   => 'test_connection',
                    'target_id'=> $server->id,
                ];
            }
        }

        foreach ($sshKeys as $key) {
            if ($key->status === 'active' && $key->created_at->lt(now()->subDays(90))) {
                $findings[] = [
                    'severity' => 'LOW',
                    'server'   => $key->label,
                    'issue'    => 'کلید SSH بیش از ۹۰ روز rotate نشده',
                    'action'   => 'rotate_key',
                    'target_id'=> $key->id,
                ];
            }
        }

        // Sort by severity
        usort($findings, function ($a, $b) {
            $order = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
            return ($order[$a['severity']] ?? 9) <=> ($order[$b['severity']] ?? 9);
        });

        return $findings;
    }
}
