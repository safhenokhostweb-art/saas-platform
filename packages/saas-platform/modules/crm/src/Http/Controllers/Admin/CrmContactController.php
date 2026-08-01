<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Crm\Http\Controllers\Concerns\FiltersCrmListings;
use SaaSPlatform\Modules\Crm\Models\CrmCompany;
use SaaSPlatform\Modules\Crm\Models\CrmContact;
use SaaSPlatform\Modules\Crm\Models\CrmTag;

class CrmContactController extends Controller
{
    use FiltersCrmListings;

    public function index(int $siteId, Request $request)
    {
        $contacts = $this->applyListFilters(
            CrmContact::where('site_id', $siteId)->with('company'),
            $request,
            ['first_name', 'last_name', 'created_at', 'lifecycle_stage'],
        );

        $companies = CrmCompany::where('site_id', $siteId)->orderBy('name')->get();
        $tags = CrmTag::where('site_id', $siteId)->orderBy('name')->get();

        return view('crm::admin.contacts.index', compact('siteId', 'contacts', 'companies', 'tags'));
    }

    public function store(Request $request, int $siteId)
    {
        $data = $this->validated($request, $siteId);
        $data['site_id'] = $siteId;

        CrmContact::create($data);

        return redirect()->route('crm.admin.contacts.index', $siteId)->with('success', 'مخاطب ثبت شد.');
    }

    public function update(Request $request, int $siteId, CrmContact $contact)
    {
        abort_unless($contact->site_id === $siteId, 404);

        $contact->update($this->validated($request, $siteId));

        return redirect()->route('crm.admin.contacts.index', $siteId)->with('success', 'مخاطب بروزرسانی شد.');
    }

    public function destroy(int $siteId, CrmContact $contact)
    {
        abort_unless($contact->site_id === $siteId, 404);
        $contact->delete();

        return redirect()->route('crm.admin.contacts.index', $siteId)->with('success', 'مخاطب حذف شد.');
    }

    public function toggleFavorite(int $siteId, CrmContact $contact)
    {
        abort_unless($contact->site_id === $siteId, 404);
        $contact->toggleFavorite();

        return back();
    }

    public function syncTags(Request $request, int $siteId, CrmContact $contact)
    {
        abort_unless($contact->site_id === $siteId, 404);

        $data = $request->validate(['tags' => 'nullable|array', 'tags.*' => 'integer']);
        $contact->syncTags($data['tags'] ?? []);

        return back();
    }

    public function bulk(Request $request, int $siteId)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'action' => 'required|in:archive,unarchive,favorite,unfavorite,delete',
        ]);

        $count = $this->applyBulkAction(CrmContact::class, $siteId, $data['ids'], $data['action']);

        return back()->with('success', "{$count} مورد بروزرسانی شد.");
    }

    private function validated(Request $request, int $siteId): array
    {
        return $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:64',
            'job_title' => 'nullable|string|max:150',
            'lifecycle_stage' => 'nullable|in:lead,prospect,customer',
            'company_id' => 'nullable|integer|exists:crm_companies,id',
            'owner_user_id' => 'nullable|integer',
        ]);
    }
}
