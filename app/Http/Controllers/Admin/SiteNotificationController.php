<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSiteNotification;
use App\Models\Site;
use Illuminate\Http\Request;

/**
 * مدیریت اعلان‌های ارسالی از پلتفرم برای هر سایت مشتری.
 */
class SiteNotificationController extends Controller
{
    public function index(Site $site)
    {
        $notifications = PlatformSiteNotification::where('site_id', $site->id)
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        return view('admin.sites.notifications.index', compact('site', 'notifications'));
    }

    public function create(Site $site)
    {
        return view('admin.sites.notifications.create', compact('site'));
    }

    /**
     * فرم ارسال اعلان به یک یا چند سایت همزمان.
     */
    public function createBulk()
    {
        $sites = Site::where('is_active', true)->orderBy('title')->get();
        return view('admin.sites.notifications.create-bulk', compact('sites'));
    }

    /**
     * ذخیره اعلان برای چند سایت انتخاب‌شده.
     */
    public function storeBulk(Request $request)
    {
        $data = $request->validate([
            'site_ids' => 'required|array',
            'site_ids.*' => 'required|integer|exists:sites,id',
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'type' => 'required|in:info,success,warning,danger',
            'link' => 'nullable|string|max:500',
            'link_text' => 'nullable|string|max:100',
            'image_path' => 'nullable|string|max:500',
            'position' => 'required|in:top,left,right',
            'image_position' => 'required|in:background,left,right',
            'audience' => 'required|in:admin,public',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'starts_at' => 'nullable|string',
            'expires_at' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        $data['priority'] = $data['priority'] ?? 'normal';
        $siteIds = array_unique($data['site_ids']);
        $data['is_active'] = $request->boolean('is_active');
        $data['starts_at'] = !empty($data['starts_at']) ? \App\Helpers\Jalali::toCarbon($data['starts_at']) : null;
        $data['expires_at'] = !empty($data['expires_at']) ? \App\Helpers\Jalali::toCarbon($data['expires_at']) : null;
        unset($data['site_ids']);

        $created = 0;
        foreach ($siteIds as $siteId) {
            $data['site_id'] = $siteId;
            $data['order'] = PlatformSiteNotification::where('site_id', $siteId)->max('order') + 1;
            PlatformSiteNotification::create($data);
            $created++;
        }

        return redirect()->route('admin.sites.index')
            ->with('success', "اعلان به {$created} سایت ارسال شد.");
    }

    public function store(Request $request, Site $site)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'type' => 'required|in:info,success,warning,danger',
            'link' => 'nullable|string|max:500',
            'link_text' => 'nullable|string|max:100',
            'image_path' => 'nullable|string|max:500',
            'position' => 'required|in:top,left,right',
            'image_position' => 'required|in:background,left,right',
            'audience' => 'required|in:admin,public',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'starts_at' => 'nullable|string',
            'expires_at' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        $data['priority'] = $data['priority'] ?? 'normal';
        $data['site_id'] = $site->id;
        $data['is_active'] = $request->boolean('is_active');
        $data['starts_at'] = !empty($data['starts_at']) ? \App\Helpers\Jalali::toCarbon($data['starts_at']) : null;
        $data['expires_at'] = !empty($data['expires_at']) ? \App\Helpers\Jalali::toCarbon($data['expires_at']) : null;
        $data['order'] = PlatformSiteNotification::where('site_id', $site->id)->max('order') + 1;
        PlatformSiteNotification::create($data);
        return redirect()->route('admin.sites.notifications.index', $site)
            ->with('success', 'اعلان ارسال شد.');
    }

    public function edit(Site $site, PlatformSiteNotification $notification)
    {
        if ($notification->site_id !== $site->id) {
            abort(404);
        }
        return view('admin.sites.notifications.edit', compact('site', 'notification'));
    }

    public function update(Request $request, Site $site, PlatformSiteNotification $notification)
    {
        if ($notification->site_id !== $site->id) {
            abort(404);
        }
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'type' => 'required|in:info,success,warning,danger',
            'link' => 'nullable|string|max:500',
            'link_text' => 'nullable|string|max:100',
            'image_path' => 'nullable|string|max:500',
            'position' => 'required|in:top,left,right',
            'image_position' => 'required|in:background,left,right',
            'audience' => 'required|in:admin,public',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'starts_at' => 'nullable|string',
            'expires_at' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        $data['priority'] = $data['priority'] ?? 'normal';
        $data['is_active'] = $request->boolean('is_active');
        $data['starts_at'] = !empty($data['starts_at']) ? \App\Helpers\Jalali::toCarbon($data['starts_at']) : null;
        $data['expires_at'] = !empty($data['expires_at']) ? \App\Helpers\Jalali::toCarbon($data['expires_at']) : null;
        $notification->update($data);
        return redirect()->route('admin.sites.notifications.index', $site)
            ->with('success', 'اعلان به‌روزرسانی شد.');
    }

    public function destroy(Site $site, PlatformSiteNotification $notification)
    {
        if ($notification->site_id !== $site->id) {
            abort(404);
        }
        $notification->delete();
        return redirect()->route('admin.sites.notifications.index', $site)
            ->with('success', 'اعلان حذف شد.');
    }

    /** آپلود تصویر اعلان */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|file|max:5120|mimes:jpeg,jpg,png,gif,webp',
        ]);
        $path = $request->file('image')->store('notifications', 'public');
        return response()->json(['path' => $path, 'url' => '/storage/' . $path]);
    }
}
