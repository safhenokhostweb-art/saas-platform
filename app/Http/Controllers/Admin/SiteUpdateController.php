<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\Jalali;
use App\Models\Site;
use App\Models\SiteUpdate;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class SiteUpdateController extends Controller
{
    public function index(Site $site)
    {
        $site->load('siteUpdates');
        return view('admin.sites.updates', compact('site'));
    }

    public function store(Request $request, Site $site)
    {
        $request->validate([
            'version_label' => 'required|string|max:100',
            'distributed_at' => 'nullable|string',
            'notes' => 'nullable|string|max:1000',
        ]);

        $distributedAt = now();
        if ($request->filled('distributed_at') && Jalali::isJalaliString($request->distributed_at)) {
            $carbon = Jalali::toCarbon($request->distributed_at);
            if ($carbon) {
                $distributedAt = $carbon;
            }
        }

        $update = $site->siteUpdates()->create([
            'version_label' => $request->version_label,
            'distributed_at' => $distributedAt,
            'notes' => $request->notes,
        ]);

        ActivityLogger::log('site_update.distributed', 'توزیع بروزرسانی ' . $update->version_label . ' برای سایت: ' . $site->title, $update);

        return back()->with('success', 'بروزرسانی ثبت شد.');
    }
}
