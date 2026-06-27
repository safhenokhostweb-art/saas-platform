<?php

namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\RemoteConnectionService;
use Illuminate\Http\Request;

class CustomerRemoteServerAdminController extends Controller
{
    public function __construct(
        private readonly CredentialVault $vault,
        private readonly RemoteConnectionService $connectionService,
    ) {}

    public function index()
    {
        $servers = CustomerRemoteServer::withCount('installations')
            ->orderBy('label')
            ->paginate(20);

        return view('admin.infrastructure.customer-deployment.servers.index', compact('servers'));
    }

    public function create()
    {
        return view('admin.infrastructure.customer-deployment.servers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label'       => 'required|string|max:100',
            'type'        => 'required|in:ssh',
            'host'        => 'required|string|max:255',
            'port'        => 'required|integer|min:1|max:65535',
            'username'    => 'required|string|max:100',
            'auth_method' => 'required|in:key,password',
            'private_key' => 'required_if:auth_method,key|nullable|string',
            'password'    => 'required_if:auth_method,password|nullable|string|max:500',
        ]);

        $data = [
            'label'       => $validated['label'],
            'type'        => $validated['type'],
            'host'        => $validated['host'],
            'port'        => (int) $validated['port'],
            'username'    => $validated['username'],
            'auth_method' => $validated['auth_method'],
        ];

        if ($validated['auth_method'] === 'key' && !empty($validated['private_key'])) {
            $data['private_key_encrypted'] = $this->vault->encrypt($validated['private_key']);
        }
        if ($validated['auth_method'] === 'password' && !empty($validated['password'])) {
            $data['password_encrypted'] = $this->vault->encrypt($validated['password']);
        }

        CustomerRemoteServer::create($data);

        return redirect()
            ->route('admin.infrastructure.customer-deployment.servers.index')
            ->with('success', 'سرور با موفقیت اضافه شد.');
    }

    public function edit(CustomerRemoteServer $server)
    {
        return view('admin.infrastructure.customer-deployment.servers.edit', compact('server'));
    }

    public function update(Request $request, CustomerRemoteServer $server)
    {
        $validated = $request->validate([
            'label'       => 'required|string|max:100',
            'host'        => 'required|string|max:255',
            'port'        => 'required|integer|min:1|max:65535',
            'username'    => 'required|string|max:100',
            'auth_method' => 'required|in:key,password',
            'private_key' => 'nullable|string',
            'password'    => 'nullable|string|max:500',
        ]);

        $data = [
            'label'       => $validated['label'],
            'host'        => $validated['host'],
            'port'        => (int) $validated['port'],
            'username'    => $validated['username'],
            'auth_method' => $validated['auth_method'],
        ];

        // Only update credentials if new ones are provided
        if ($validated['auth_method'] === 'key' && !empty($validated['private_key'])) {
            $data['private_key_encrypted'] = $this->vault->encrypt($validated['private_key']);
        }
        if ($validated['auth_method'] === 'password' && !empty($validated['password'])) {
            $data['password_encrypted'] = $this->vault->encrypt($validated['password']);
        }

        $server->update($data);

        return redirect()
            ->route('admin.infrastructure.customer-deployment.servers.index')
            ->with('success', 'سرور با موفقیت ویرایش شد.');
    }

    public function destroy(CustomerRemoteServer $server)
    {
        if ($server->installations()->whereIn('status', ['pending', 'running'])->exists()) {
            return back()->with('error', 'این سرور در حال نصب فعال است. ابتدا نصب را متوقف کنید.');
        }

        $server->delete();

        return redirect()
            ->route('admin.infrastructure.customer-deployment.servers.index')
            ->with('success', 'سرور حذف شد.');
    }

    public function testConnection(Request $request, CustomerRemoteServer $server)
    {
        try {
            $result = $this->connectionService->testAndRecord($server);

            if ($result->connected) {
                return response()->json([
                    'success'     => true,
                    'message'     => 'اتصال موفق',
                    'php_version' => $result->phpVersion,
                    'disk_free'   => $result->diskFreeBytes ? round($result->diskFreeBytes / 1024 / 1024 / 1024, 2) . ' GB' : null,
                    'fingerprint' => $result->fingerprint,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result->error ?? 'خطا در اتصال',
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
