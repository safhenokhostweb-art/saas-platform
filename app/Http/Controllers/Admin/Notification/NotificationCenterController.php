<?php
namespace App\Http\Controllers\Admin\Notification;

use App\Http\Controllers\Controller;
use App\Models\NotificationCenterLog;
use App\Models\Site;
use App\Services\Notification\NotificationCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationCenterController extends Controller
{
    public function __construct(private NotificationCenterService $nc) {}

    // ── Main panel ────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = NotificationCenterLog::with('site', 'creator')
            ->orderByDesc('created_at');

        // Filters
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }
        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }
        if ($audience = $request->get('audience')) {
            $query->where('audience_type', $audience);
        }
        if ($status = $request->get('status')) {
            match ($status) {
                'unread' => $query->whereNull('read_at'),
                'read'   => $query->whereNotNull('read_at'),
                default  => null,
            };
        }
        if ($period = $request->get('period')) {
            match ($period) {
                'today' => $query->whereDate('created_at', today()),
                'week'  => $query->where('created_at', '>=', now()->startOfWeek()),
                'month' => $query->where('created_at', '>=', now()->startOfMonth()),
                default => null,
            };
        }
        if ($search = $request->get('search')) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%"));
        }

        $logs  = $query->paginate(30)->withQueryString();
        $stats = $this->nc->stats();
        $sites = Site::where('is_active', true)->orderBy('title')->get(['id', 'title']);

        return view('admin.notification-center.index', compact('logs', 'stats', 'sites'));
    }

    // ── Send notification form ─────────────────────────────────────────────

    public function create()
    {
        $sites = Site::where('is_active', true)->orderBy('title')->get(['id', 'title']);
        return view('admin.notification-center.create', compact('sites'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'          => 'required|in:' . implode(',', array_keys(NotificationCenterLog::TYPES)),
            'priority'      => 'required|in:critical,high,medium,low',
            'title'         => 'required|string|max:255',
            'message'       => 'nullable|string|max:2000',
            'audience_type' => 'required|in:' . implode(',', array_keys(NotificationCenterLog::AUDIENCES)),
            'site_id'       => 'nullable|exists:sites,id',
            'channel'       => 'required|in:in_app,sms,both,silent',
            'link'          => 'nullable|url|max:500',
        ]);

        // site_id only relevant for site_admin audience
        if ($validated['audience_type'] !== 'site_admin') {
            $validated['site_id'] = null;
        }

        $log = $this->nc->notify($validated);

        return redirect()
            ->route('admin.notification-center.index')
            ->with('success', "اعلان «{$log->title}» با موفقیت ارسال شد.");
    }

    // ── AJAX actions ──────────────────────────────────────────────────────

    public function markRead(int $id): JsonResponse
    {
        $this->nc->markRead($id);
        return response()->json(['ok' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        $count = $this->nc->markAllRead();
        return response()->json(['ok' => true, 'count' => $count]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->nc->delete($id);
        return response()->json(['ok' => true]);
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->nc->stats());
    }

    // ── Bulk resolve (for monitoring integrations) ─────────────────────────

    public function bulkMarkRead(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            NotificationCenterLog::whereIn('id', $ids)->update(['read_at' => now()]);
        }
        return response()->json(['ok' => true, 'count' => count($ids)]);
    }
}
