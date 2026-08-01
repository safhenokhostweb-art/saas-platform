<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Crm\Http\Controllers\Concerns\ResolvesCrmSubject;
use SaaSPlatform\Modules\Crm\Models\CrmActivity;

class CrmActivityController extends Controller
{
    use ResolvesCrmSubject;

    /** Timeline for a single subject (Contact/Company/Lead/Opportunity) — see Part 7's Customer Profile page for the aggregated view. */
    public function index(int $siteId, string $subjectType, int $subjectId)
    {
        $subject = $this->resolveSubject($subjectType, $subjectId, $siteId);
        $activities = $subject->activities()->with('attachments')->paginate((int) config('crm.pagination', 20));

        return view('crm::admin.activities.index', compact('siteId', 'subjectType', 'subjectId', 'subject', 'activities'));
    }

    public function store(Request $request, int $siteId, string $subjectType, int $subjectId)
    {
        $subject = $this->resolveSubject($subjectType, $subjectId, $siteId);

        $data = $request->validate([
            'type' => 'required|in:call,meeting,email,sms,note,system',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'occurred_at' => 'nullable|date',
        ]);
        $data['site_id'] = $siteId;
        $data['occurred_at'] = $data['occurred_at'] ?? now();
        $data['created_by'] = $request->user()?->id;

        $subject->activities()->create($data);

        return back()->with('success', 'رویداد ثبت شد.');
    }

    public function destroy(int $siteId, CrmActivity $activity)
    {
        abort_unless($activity->site_id === $siteId, 404);
        $activity->delete();

        return back()->with('success', 'رویداد حذف شد.');
    }
}
