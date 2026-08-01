<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Crm\Http\Controllers\Concerns\ResolvesCrmSubject;
use SaaSPlatform\Modules\Crm\Models\CrmTask;

class CrmTaskController extends Controller
{
    use ResolvesCrmSubject;

    /** Site-wide task list (e.g. "my tasks due soon"), not scoped to a single subject. */
    public function index(int $siteId, Request $request)
    {
        $query = CrmTask::where('site_id', $siteId);

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        if ($request->boolean('due_soon')) {
            $query->dueSoon();
        } elseif (!$request->filled('status')) {
            $query->pending();
        } elseif ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $tasks = $query->orderBy('due_at')->paginate((int) config('crm.pagination', 20))->withQueryString();

        return view('crm::admin.tasks.index', compact('siteId', 'tasks'));
    }

    public function store(Request $request, int $siteId, string $subjectType, int $subjectId)
    {
        $subject = $this->resolveSubject($subjectType, $subjectId, $siteId);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_at' => 'nullable|date',
            'remind_at' => 'nullable|date',
            'assigned_to' => 'nullable|integer',
        ]);
        $data['site_id'] = $siteId;
        $data['created_by'] = $request->user()?->id;

        $subject->tasks()->create($data);

        return back()->with('success', 'وظیفه ثبت شد.');
    }

    public function complete(int $siteId, CrmTask $task)
    {
        abort_unless($task->site_id === $siteId, 404);
        $task->complete();

        return back()->with('success', 'وظیفه تکمیل شد.');
    }

    public function destroy(int $siteId, CrmTask $task)
    {
        abort_unless($task->site_id === $siteId, 404);
        $task->delete();

        return back()->with('success', 'وظیفه حذف شد.');
    }
}
