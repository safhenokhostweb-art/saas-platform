<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Crm\Models\CrmTag;

class CrmTagController extends Controller
{
    public function index(int $siteId)
    {
        $tags = CrmTag::where('site_id', $siteId)->orderBy('name')->get();

        return view('crm::admin.tags.index', compact('siteId', 'tags'));
    }

    public function store(Request $request, int $siteId)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
        ]);
        $data['site_id'] = $siteId;

        CrmTag::create($data);

        return redirect()->route('crm.admin.tags.index', $siteId)->with('success', 'برچسب ثبت شد.');
    }

    public function destroy(int $siteId, CrmTag $tag)
    {
        abort_unless($tag->site_id === $siteId, 404);
        $tag->delete();

        return redirect()->route('crm.admin.tags.index', $siteId)->with('success', 'برچسب حذف شد.');
    }
}
