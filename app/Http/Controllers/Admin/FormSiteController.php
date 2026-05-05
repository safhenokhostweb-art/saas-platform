<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Site;
use Illuminate\Http\Request;

class FormSiteController extends Controller
{
    public function edit(Form $form)
    {
        $sites = Site::latest()->get();
        $attached = $form->sites()->wherePivot('is_active', true)->pluck('sites.id')->toArray();

        return view('admin.forms.sites', compact('form', 'sites', 'attached'));
    }

    public function update(Request $request, Form $form)
    {
        $data = $request->validate([
            'sites' => 'nullable|array',
            'sites.*' => 'exists:sites,id',
        ]);

        $requestedIds = $data['sites'] ?? [];
        $allSiteIds = Site::pluck('id');
        $sync = $allSiteIds->mapWithKeys(fn ($id) => [$id => ['is_active' => in_array($id, $requestedIds)]])->toArray();
        $form->sites()->sync($sync);

        return back()->with('success', 'اتصال فرم به سایت‌ها بروزرسانی شد');
    }

    /** فعال/غیرفعال کردن فرم در یک سایت */
    public function toggleSite(Request $request, Form $form, Site $site)
    {
        $current = $form->sites()->where('site_id', $site->id)->first();
        $isActive = $current ? ! ($current->pivot->is_active ?? false) : true;
        $form->sites()->syncWithoutDetaching([$site->id => ['is_active' => $isActive]]);

        return back()->with('success', $isActive ? 'فرم در این سایت فعال شد.' : 'فرم در این سایت غیرفعال شد.');
    }
}
