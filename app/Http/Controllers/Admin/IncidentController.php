<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\InfraIncident;
use App\Models\Site;
use App\Services\Infrastructure\Monitoring\IncidentLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IncidentController extends Controller
{
    public function __construct(private IncidentLifecycleService $lifecycle) {}

    public function index(Request $request)
    {
        $query = InfraIncident::with(['site', 'assignedTo'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Default: show active only
            $query->active();
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->site_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->status === 'all') {
            $query = InfraIncident::with(['site', 'assignedTo'])->latest();
            if ($request->filled('severity')) $query->where('severity', $request->severity);
            if ($request->filled('site_id'))  $query->where('site_id', $request->site_id);
            if ($request->filled('type'))     $query->where('type', $request->type);
        }

        $incidents = $query->paginate(25)->withQueryString();

        $stats = [
            'open'          => InfraIncident::open()->count(),
            'investigating' => InfraIncident::investigating()->count(),
            'critical'      => InfraIncident::active()->critical()->count(),
            'resolved_today'=> InfraIncident::resolved()->whereDate('resolved_at', today())->count(),
        ];

        $sites = Site::where('is_active', true)->orderBy('title')->get(['id', 'title', 'domain']);

        return view('admin.incidents.index', compact('incidents', 'stats', 'sites'));
    }

    public function show(int $id)
    {
        $incident = InfraIncident::with(['site', 'assignedTo', 'notes.user'])->findOrFail($id);
        $admins   = \App\Models\User::where('is_admin', true)->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.incidents.show', compact('incident', 'admins'));
    }

    public function investigate(Request $request, int $id)
    {
        $incident = InfraIncident::findOrFail($id);
        $user     = Auth::user();

        $this->lifecycle->investigate($incident, $user, $request->input('note'));

        return back()->with('success', 'حادثه در حال بررسی است.');
    }

    public function resolve(Request $request, int $id)
    {
        $incident = InfraIncident::findOrFail($id);
        $user     = Auth::user();

        $this->lifecycle->resolve($incident, $user, $request->input('note'));

        return back()->with('success', 'حادثه برطرف شد. مشتری مطلع خواهد شد.');
    }

    public function close(Request $request, int $id)
    {
        $incident = InfraIncident::findOrFail($id);
        $user     = Auth::user();

        $this->lifecycle->close($incident, $user, $request->input('note'));

        return back()->with('success', 'حادثه بسته شد.');
    }

    public function addNote(Request $request, int $id)
    {
        $request->validate(['note' => 'required|string|max:5000']);

        $incident = InfraIncident::findOrFail($id);
        $user     = Auth::user();

        $this->lifecycle->addNote($incident, $user, $request->note);

        return back()->with('success', 'یادداشت اضافه شد.');
    }

    public function deleteNote(int $incidentId, int $noteId): \Illuminate\Http\RedirectResponse
    {
        $note = \App\Models\Infrastructure\InfraIncidentNote::where('incident_id', $incidentId)
            ->where('id', $noteId)
            ->where('is_system', false)
            ->firstOrFail();

        $note->delete();

        return back()->with('success', 'یادداشت حذف شد.');
    }
}
