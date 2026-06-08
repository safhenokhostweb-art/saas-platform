<?php
namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\InfraDatabaseServer;
use App\Models\Infrastructure\InfraServer;
use Illuminate\Http\Request;

class InfraDatabaseController extends Controller
{
    public function index()
    {
        $databases = InfraDatabaseServer::with('server')->orderBy('engine')->orderBy('name')->get();

        return view('admin.infrastructure.databases.index', compact('databases'));
    }

    public function create()
    {
        $servers = InfraServer::where('is_active', true)->orderBy('name')->get();
        return view('admin.infrastructure.databases.form', [
            'database' => new InfraDatabaseServer(),
            'servers'  => $servers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        InfraDatabaseServer::create($data);

        return redirect()->route('admin.infrastructure.databases.index')
            ->with('success', 'سرور دیتابیس با موفقیت ثبت شد.');
    }

    public function edit(InfraDatabaseServer $database)
    {
        $servers = InfraServer::where('is_active', true)->orderBy('name')->get();
        return view('admin.infrastructure.databases.form', compact('database', 'servers'));
    }

    public function update(Request $request, InfraDatabaseServer $database)
    {
        $data = $this->validated($request);
        $database->update($data);

        return redirect()->route('admin.infrastructure.databases.index')
            ->with('success', 'سرور دیتابیس با موفقیت بروزرسانی شد.');
    }

    public function destroy(InfraDatabaseServer $database)
    {
        $database->delete();
        return back()->with('success', 'سرور دیتابیس حذف شد.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'                  => 'required|string|max:191',
            'server_id'             => 'nullable|exists:infra_servers,id',
            'engine'                => 'required|in:mysql,pgsql,sqlite,redis,mongodb',
            'version'               => 'nullable|string|max:50',
            'host'                  => 'nullable|string|max:255',
            'port'                  => 'nullable|integer|min:1|max:65535',
            'environment'           => 'required|in:production,staging,development',
            'size_mb'               => 'nullable|integer|min:0',
            'size_mb_prev_month'    => 'nullable|integer|min:0',
            'active_connections'    => 'nullable|integer|min:0',
            'max_connections'       => 'nullable|integer|min:0',
            'status'                => 'required|in:online,offline,degraded,maintenance',
            'last_backup_at'        => 'nullable|date',
            'backup_storage'        => 'nullable|string|max:191',
            'backup_frequency'      => 'nullable|string|max:100',
            'replication_enabled'   => 'boolean',
            'replication_role'      => 'nullable|in:primary,replica',
            'notes'                 => 'nullable|string',
            'is_active'             => 'boolean',
        ]);
    }
}
