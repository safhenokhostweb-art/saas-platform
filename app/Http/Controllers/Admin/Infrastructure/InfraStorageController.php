<?php
namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\InfraStorageProvider;
use Illuminate\Http\Request;

class InfraStorageController extends Controller
{
    public function index()
    {
        $providers = InfraStorageProvider::orderBy('purpose')->orderBy('name')->get();

        $totalCapacityGb = $providers->sum('capacity_gb');
        $totalUsedGb     = $providers->sum('used_gb');

        return view('admin.infrastructure.storage.index', compact('providers', 'totalCapacityGb', 'totalUsedGb'));
    }

    public function create()
    {
        return view('admin.infrastructure.storage.form', ['provider' => new InfraStorageProvider()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        InfraStorageProvider::create($data);

        return redirect()->route('admin.infrastructure.storage.index')
            ->with('success', 'سرویس استوریج با موفقیت ثبت شد.');
    }

    public function edit(InfraStorageProvider $storage)
    {
        return view('admin.infrastructure.storage.form', ['provider' => $storage]);
    }

    public function update(Request $request, InfraStorageProvider $storage)
    {
        $data = $this->validated($request);
        $storage->update($data);

        return redirect()->route('admin.infrastructure.storage.index')
            ->with('success', 'سرویس استوریج با موفقیت بروزرسانی شد.');
    }

    public function destroy(InfraStorageProvider $storage)
    {
        $storage->delete();
        return back()->with('success', 'سرویس استوریج حذف شد.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'                 => 'required|string|max:191',
            'driver'               => 'required|in:local,s3,minio,ftp,sftp',
            'endpoint'             => 'nullable|url|max:500',
            'bucket'               => 'nullable|string|max:191',
            'region'               => 'nullable|string|max:100',
            'disk_name'            => 'nullable|string|max:100',
            'purpose'              => 'required|in:primary,backup,cdn,tenant-uploads,exports',
            'capacity_gb'          => 'nullable|integer|min:0',
            'used_gb'              => 'nullable|integer|min:0',
            'used_gb_prev_month'   => 'nullable|integer|min:0',
            'monthly_cost'         => 'nullable|numeric|min:0',
            'currency'             => 'nullable|string|size:3',
            'status'               => 'required|in:active,degraded,full,inactive',
            'last_measured_at'     => 'nullable|date',
            'notes'                => 'nullable|string',
            'is_active'            => 'boolean',
        ]);
    }
}
