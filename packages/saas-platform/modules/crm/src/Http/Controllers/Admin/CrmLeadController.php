<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Crm\Http\Controllers\Concerns\FiltersCrmListings;
use SaaSPlatform\Modules\Crm\Models\CrmLead;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;
use SaaSPlatform\Modules\Crm\Services\CrmLeadConversionService;

class CrmLeadController extends Controller
{
    use FiltersCrmListings;

    public function index(int $siteId, Request $request)
    {
        $leads = $this->applyListFilters(
            CrmLead::where('site_id', $siteId),
            $request,
            ['first_name', 'created_at', 'status'],
            extraFilter: function ($query, Request $req) {
                if ($req->filled('status')) {
                    $query->where('status', $req->string('status')->toString());
                }
            },
        );

        return view('crm::admin.leads.index', compact('siteId', 'leads'));
    }

    public function store(Request $request, int $siteId)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:64',
            'company_name' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:100',
            'owner_user_id' => 'nullable|integer',
        ]);
        $data['site_id'] = $siteId;

        CrmLead::create($data);

        return redirect()->route('crm.admin.leads.index', $siteId)->with('success', 'سرنخ ثبت شد.');
    }

    public function update(Request $request, int $siteId, CrmLead $lead)
    {
        abort_unless($lead->site_id === $siteId, 404);

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:64',
            'company_name' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:100',
            'status' => 'nullable|in:new,contacted,qualified,unqualified',
            'owner_user_id' => 'nullable|integer',
        ]);

        $lead->update($data);

        return redirect()->route('crm.admin.leads.index', $siteId)->with('success', 'سرنخ بروزرسانی شد.');
    }

    public function destroy(int $siteId, CrmLead $lead)
    {
        abort_unless($lead->site_id === $siteId, 404);
        $lead->delete();

        return redirect()->route('crm.admin.leads.index', $siteId)->with('success', 'سرنخ حذف شد.');
    }

    public function convert(Request $request, int $siteId, CrmLead $lead, CrmLeadConversionService $service)
    {
        abort_unless($lead->site_id === $siteId, 404);

        $data = $request->validate([
            'create_company' => 'nullable|boolean',
            'create_opportunity' => 'nullable|boolean',
            'opportunity_name' => 'nullable|string|max:255',
            'opportunity_amount' => 'nullable|integer|min:0',
            'pipeline_id' => 'nullable|integer|exists:crm_pipelines,id',
            'stage_id' => 'nullable|integer|exists:crm_stages,id',
        ]);

        $contact = $service->convert($lead, $data);

        return redirect()
            ->route('crm.admin.contacts.index', $siteId)
            ->with('success', "سرنخ به مخاطب «{$contact->full_name}» تبدیل شد.");
    }

    public function bulk(Request $request, int $siteId)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'action' => 'required|in:archive,unarchive,favorite,unfavorite,delete',
        ]);

        $count = $this->applyBulkAction(CrmLead::class, $siteId, $data['ids'], $data['action']);

        return back()->with('success', "{$count} مورد بروزرسانی شد.");
    }
}
