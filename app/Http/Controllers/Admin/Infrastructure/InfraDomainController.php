<?php
namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\InfraDomain;
use App\Models\Infrastructure\InfraServer;
use App\Models\Site;
use Illuminate\Http\Request;

class InfraDomainController extends Controller
{
    public function index(Request $request)
    {
        $query = InfraDomain::with(['site', 'server']);

        if ($search = $request->get('search')) {
            $query->where('domain', 'like', "%{$search}%");
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($request->boolean('expiring')) {
            $query->whereNotNull('expires_at')
                  ->whereDate('expires_at', '<=', now()->addDays(30));
        }

        if ($request->boolean('ssl_expiring')) {
            $query->where('ssl_enabled', true)
                  ->whereNotNull('ssl_expires_at')
                  ->whereDate('ssl_expires_at', '<=', now()->addDays(14));
        }

        $domains = $query->orderBy('expires_at')->paginate(20);

        return view('admin.infrastructure.domains.index', compact('domains'));
    }

    public function create()
    {
        $servers = InfraServer::where('is_active', true)->orderBy('name')->get();
        $sites   = Site::orderBy('title')->get();
        return view('admin.infrastructure.domains.form', [
            'domain'  => new InfraDomain(),
            'servers' => $servers,
            'sites'   => $sites,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->convertJalaliDates($this->validated($request), ['registered_at', 'expires_at', 'ssl_expires_at']);
        InfraDomain::create($data);

        return redirect()->route('admin.infrastructure.domains.index')
            ->with('success', 'دامنه با موفقیت ثبت شد.');
    }

    public function edit(InfraDomain $domain)
    {
        $servers = InfraServer::where('is_active', true)->orderBy('name')->get();
        $sites   = Site::orderBy('title')->get();
        return view('admin.infrastructure.domains.form', compact('domain', 'servers', 'sites'));
    }

    public function update(Request $request, InfraDomain $domain)
    {
        $data = $this->convertJalaliDates($this->validated($request, $domain->id), ['registered_at', 'expires_at', 'ssl_expires_at']);
        $domain->update($data);

        return redirect()->route('admin.infrastructure.domains.index')
            ->with('success', 'دامنه با موفقیت بروزرسانی شد.');
    }

    public function destroy(InfraDomain $domain)
    {
        $domain->delete();
        return back()->with('success', 'دامنه حذف شد.');
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

    private function validated(Request $request, ?int $excludeId = null): array
    {
        return $request->validate([
            'domain'             => 'required|string|max:255|unique:infra_domains,domain,' . $excludeId,
            'type'               => 'required|in:tenant,platform,cdn,api,mail',
            'site_id'            => 'nullable|exists:sites,id',
            'server_id'          => 'nullable|exists:infra_servers,id',
            'registrar'          => 'nullable|string|max:100',
            'registered_at'      => 'nullable|string|max:20',
            'expires_at'         => 'nullable|string|max:20',
            'ssl_enabled'        => 'boolean',
            'ssl_provider'       => 'nullable|string|max:100',
            'ssl_expires_at'     => 'nullable|string|max:20',
            'ssl_status'         => 'required|in:valid,expiring_soon,expired,unknown',
            'dns_provider'       => 'nullable|string|max:100',
            'dns_notes'          => 'nullable|string',
            'status'             => 'required|in:active,inactive,parked,transferred',
            'redirects_to_https' => 'boolean',
            'notes'              => 'nullable|string',
        ]);
    }
}
