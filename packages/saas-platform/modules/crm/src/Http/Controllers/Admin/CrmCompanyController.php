<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Crm\Http\Controllers\Concerns\FiltersCrmListings;
use SaaSPlatform\Modules\Crm\Models\CrmCompany;
use SaaSPlatform\Modules\Crm\Models\CrmTag;

/**
 * Phase 19 — Part 4/7: takes a plain int $siteId route parameter (never
 * a host Eloquent Site model binding) — matches SEO Advanced/Tickets' convention
 * so this module stays usable standalone once deployed to a customer
 * site. Every query is explicitly scoped to $siteId (see
 * FiltersCrmListings' docblock for why SiteTenantScope alone isn't
 * sufficient in this web-admin context).
 */
class CrmCompanyController extends Controller
{
    use FiltersCrmListings;

    public function index(int $siteId, Request $request)
    {
        $companies = $this->applyListFilters(
            CrmCompany::where('site_id', $siteId),
            $request,
            ['name', 'created_at', 'lifecycle_stage'],
        );

        $tags = CrmTag::where('site_id', $siteId)->orderBy('name')->get();

        return view('crm::admin.companies.index', compact('siteId', 'companies', 'tags'));
    }

    public function store(Request $request, int $siteId)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:64',
            'address' => 'nullable|string|max:500',
            'lifecycle_stage' => 'nullable|in:lead,prospect,customer',
            'owner_user_id' => 'nullable|integer',
        ]);
        $data['site_id'] = $siteId;

        $company = CrmCompany::create($data);

        return redirect()->route('crm.admin.companies.index', $siteId)->with('success', 'شرکت ثبت شد.');
    }

    public function update(Request $request, int $siteId, CrmCompany $company)
    {
        abort_unless($company->site_id === $siteId, 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:64',
            'address' => 'nullable|string|max:500',
            'lifecycle_stage' => 'nullable|in:lead,prospect,customer',
            'owner_user_id' => 'nullable|integer',
        ]);

        $company->update($data);

        return redirect()->route('crm.admin.companies.index', $siteId)->with('success', 'شرکت بروزرسانی شد.');
    }

    public function destroy(int $siteId, CrmCompany $company)
    {
        abort_unless($company->site_id === $siteId, 404);
        $company->delete();

        return redirect()->route('crm.admin.companies.index', $siteId)->with('success', 'شرکت حذف شد.');
    }

    public function toggleFavorite(int $siteId, CrmCompany $company)
    {
        abort_unless($company->site_id === $siteId, 404);
        $company->toggleFavorite();

        return back();
    }

    public function syncTags(Request $request, int $siteId, CrmCompany $company)
    {
        abort_unless($company->site_id === $siteId, 404);

        $data = $request->validate(['tags' => 'nullable|array', 'tags.*' => 'integer']);
        $company->syncTags($data['tags'] ?? []);

        return back();
    }

    public function bulk(Request $request, int $siteId)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'action' => 'required|in:archive,unarchive,favorite,unfavorite,delete',
        ]);

        $count = $this->applyBulkAction(CrmCompany::class, $siteId, $data['ids'], $data['action']);

        return back()->with('success', "{$count} مورد بروزرسانی شد.");
    }
}
