<?php
namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\InfraServer;
use Illuminate\Http\Request;

class InfraServerController extends Controller
{
    public function index(Request $request)
    {
        $query = InfraServer::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('hostname', 'like', "%{$search}%");
            });
        }

        if ($env = $request->get('environment')) {
            $query->where('environment', $env);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $servers = $query->withTrashed($request->boolean('trashed'))->latest()->paginate(15);

        return view('admin.infrastructure.servers.index', compact('servers'));
    }

    public function create()
    {
        return view('admin.infrastructure.servers.form', ['server' => new InfraServer()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        InfraServer::create($data);

        return redirect()->route('admin.infrastructure.servers.index')
            ->with('success', 'سرور با موفقیت ثبت شد.');
    }

    public function edit(InfraServer $server)
    {
        return view('admin.infrastructure.servers.form', compact('server'));
    }

    public function update(Request $request, InfraServer $server)
    {
        $data = $this->validated($request);
        $server->update($data);

        return redirect()->route('admin.infrastructure.servers.index')
            ->with('success', 'سرور با موفقیت بروزرسانی شد.');
    }

    public function destroy(InfraServer $server)
    {
        $server->delete();
        return back()->with('success', 'سرور حذف شد.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'               => 'required|string|max:191',
            'hostname'           => 'nullable|string|max:255',
            'ip_address'         => 'nullable|ip',
            'ipv6_address'       => 'nullable|string|max:45',
            'environment'        => 'required|in:production,staging,development',
            'roles'              => 'nullable|array',
            'roles.*'            => 'in:application,database,storage,cache,queue',
            'provider'           => 'nullable|string|max:100',
            'datacenter'         => 'nullable|string|max:100',
            'cpu_cores'          => 'nullable|integer|min:1',
            'ram_gb'             => 'nullable|integer|min:1',
            'disk_gb'            => 'nullable|integer|min:1',
            'monthly_cost'       => 'nullable|numeric|min:0',
            'currency'           => 'nullable|string|size:3',
            'os'                 => 'nullable|string|max:100',
            'status'             => 'required|in:online,offline,degraded,maintenance,unknown',
            'uptime_percent'     => 'nullable|integer|min:0|max:100',
            'last_backup_at'     => 'nullable|date',
            'last_deployment_at' => 'nullable|date',
            'notes'              => 'nullable|string',
            'is_active'          => 'boolean',
        ]);
    }
}
