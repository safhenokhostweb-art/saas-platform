<?php
namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\InfraHosting;
use App\Models\Infrastructure\InfraServer;
use Illuminate\Http\Request;

class InfraHostingController extends Controller
{
    public function index(Request $request)
    {
        $hosting = InfraHosting::with('server')
            ->orderBy('next_renewal_at')
            ->paginate(20);

        return view('admin.infrastructure.hosting.index', compact('hosting'));
    }

    public function create()
    {
        $servers = InfraServer::where('is_active', true)->orderBy('name')->get();
        return view('admin.infrastructure.hosting.form', [
            'hosting' => new InfraHosting(),
            'servers' => $servers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->convertJalaliDates($this->validated($request), ['billing_cycle_start', 'billing_cycle_end', 'next_renewal_at']);
        InfraHosting::create($data);

        return redirect()->route('admin.infrastructure.hosting.index')
            ->with('success', 'هاستینگ با موفقیت ثبت شد.');
    }

    public function edit(InfraHosting $hosting)
    {
        $servers = InfraServer::where('is_active', true)->orderBy('name')->get();
        return view('admin.infrastructure.hosting.form', compact('hosting', 'servers'));
    }

    public function update(Request $request, InfraHosting $hosting)
    {
        $data = $this->convertJalaliDates($this->validated($request), ['billing_cycle_start', 'billing_cycle_end', 'next_renewal_at']);
        $hosting->update($data);

        return redirect()->route('admin.infrastructure.hosting.index')
            ->with('success', 'هاستینگ با موفقیت بروزرسانی شد.');
    }

    public function destroy(InfraHosting $hosting)
    {
        $hosting->delete();
        return back()->with('success', 'هاستینگ حذف شد.');
    }

    private function convertJalaliDates(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (!empty($data[$field])) {
                $carbon = \App\Helpers\Jalali::toCarbon($data[$field]);
                $data[$field] = $carbon ? $carbon->format('Y-m-d') : null;
            }
        }
        return $data;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'                    => 'required|string|max:191',
            'server_id'               => 'nullable|exists:infra_servers,id',
            'provider'                => 'required|string|max:100',
            'plan'                    => 'nullable|string|max:100',
            'account_email'           => 'nullable|email|max:191',
            'panel_url'               => 'nullable|url|max:500',
            'environment'             => 'required|in:production,staging,development',
            'cpu_cores_allocated'     => 'nullable|integer|min:1',
            'ram_gb_allocated'        => 'nullable|integer|min:1',
            'disk_gb_allocated'       => 'nullable|integer|min:1',
            'bandwidth_gb_allocated'  => 'nullable|integer|min:0',
            'monthly_cost'            => 'nullable|numeric|min:0',
            'currency'                => 'nullable|string|size:3',
            'billing_cycle_start'     => 'nullable|string|max:20',
            'billing_cycle_end'       => 'nullable|string|max:20',
            'next_renewal_at'         => 'nullable|string|max:20',
            'status'                  => 'required|in:active,suspended,cancelled,pending',
            'notes'                   => 'nullable|string',
        ]);
    }
}
