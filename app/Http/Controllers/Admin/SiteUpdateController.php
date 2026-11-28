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
        $siteUpdates = $site->siteUpdates()->orderByDesc('distributed_at')->orderByDesc('id')->get();
        $fleetUpdates = SiteUpdate::fleet()->orderByDesc('release_date')->orderByDesc('id')->get();
        $allUpdates = SiteUpdate::with('site')->orderByDesc('release_date')->orderByDesc('id')->paginate(30);

        return view('admin.sites.updates', compact('site', 'siteUpdates', 'fleetUpdates', 'allUpdates'));
    }

    public function store(Request $request, Site $site)
    {
        $request->validate([
            'title'          => 'required|string|max:190',
            'version'        => 'required|string|max:100',
            'shamsi_date'    => 'nullable|string|max:32',
            'distributed_at' => 'nullable|string',
            'update_type'    => 'nullable|string|in:feature,improvement,security,fix',
            'scope'          => 'required|string|in:site,fleet',
            'notes'          => 'nullable|string|max:2000',
            'features_json'  => 'nullable|string',
        ]);

        $distributedAt = now();
        if ($request->filled('distributed_at') && Jalali::isJalaliString($request->distributed_at)) {
            $carbon = Jalali::toCarbon($request->distributed_at);
            if ($carbon) {
                $distributedAt = $carbon;
            }
        }

        $features = null;
        if ($request->filled('features_json')) {
            $decoded = json_decode($request->features_json, true);
            if (is_array($decoded)) {
                $features = $decoded;
            }
        }

        $shamsiDate = $request->shamsi_date ?: Jalali::formatFa($distributedAt);
        $isFleet = $request->input('scope') === 'fleet';
        $siteId = $isFleet ? null : $site->id;

        $update = SiteUpdate::create([
            'site_id'        => $siteId,
            'title'          => $request->title,
            'version'        => $request->version,
            'version_label'  => $request->version,
            'shamsi_date'    => $shamsiDate,
            'release_date'   => $distributedAt->toDateString(),
            'update_type'    => $request->input('update_type', 'feature'),
            'features'       => $features,
            'distributed_at' => $distributedAt,
            'notes'          => $request->notes,
            'is_published'   => true,
        ]);

        $targetName = $isFleet ? 'کل ناوگان (سراسری)' : ('سایت: ' . $site->title);
        ActivityLogger::log('site_update.distributed', 'ثبت بروزرسانی ' . $update->version . ' برای ' . $targetName, $update);

        return back()->with('success', 'بروزرسانی جدید با موفقیت ثبت شد.');
    }

    public function destroy(SiteUpdate $update)
    {
        $v = $update->version ?: $update->version_label;
        $update->delete();

        return back()->with('success', 'بروزرسانی ' . $v . ' با موفقیت حذف شد.');
    }
}
