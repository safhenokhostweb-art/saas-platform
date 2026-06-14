<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = SystemNotification::whereNull('site_id')
            ->whereNull('reseller_id')
            ->latest()
            ->paginate(30);

        return view('admin.system-alerts.index', compact('notifications'));
    }

    public function unreadCount(): JsonResponse
    {
        $count = AdminNotificationService::unreadCount();
        $items = AdminNotificationService::recentUnread(8)->map(function ($n) {
            return [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'body'       => $n->body,
                'link'       => $n->data['link'] ?? null,
                'created_at' => $n->created_at->diffForHumans(),
            ];
        });

        return response()->json(['count' => $count, 'items' => $items]);
    }

    public function markRead(int $id): JsonResponse
    {
        $notification = SystemNotification::whereNull('site_id')
            ->whereNull('reseller_id')
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        SystemNotification::whereNull('site_id')
            ->whereNull('reseller_id')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function destroy(int $id): RedirectResponse
    {
        SystemNotification::whereNull('site_id')
            ->whereNull('reseller_id')
            ->findOrFail($id)
            ->delete();

        return back()->with('success', 'اعلان حذف شد.');
    }
}
