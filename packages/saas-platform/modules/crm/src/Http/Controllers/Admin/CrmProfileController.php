<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Crm\Models\CrmContact;

/**
 * Phase 19 — Part 7: a single page bringing together everything known
 * about one Contact — latest activities, open/won/lost deals, pending
 * tasks, notes (Activities of type=note), files, and simple statistics.
 * Deliberately read-mostly; mutations happen via the entity-specific
 * controllers (Activity/Task/Attachment/Opportunity) whose forms live on
 * this same page.
 */
class CrmProfileController extends Controller
{
    public function show(int $siteId, CrmContact $contact)
    {
        abort_unless($contact->site_id === $siteId, 404);

        $contact->load(['company', 'tags']);

        $activities = $contact->activities()->with('attachments')->limit(20)->get();
        $notes = $activities->where('type', 'note');
        $deals = $contact->opportunities()->with('stage')->latest()->get();
        $tasks = $contact->tasks()->pending()->orderBy('due_at')->get();
        $files = $contact->attachments()->limit(50)->get();

        $stats = [
            'total_deals' => $deals->count(),
            'open_deals' => $deals->where('status', 'open')->count(),
            'won_deals' => $deals->where('status', 'won')->count(),
            'won_amount' => $deals->where('status', 'won')->sum('amount'),
            'open_pipeline_amount' => $deals->where('status', 'open')->sum('amount'),
            'pending_tasks' => $tasks->count(),
        ];

        return view('crm::admin.profile.show', compact(
            'siteId', 'contact', 'activities', 'notes', 'deals', 'tasks', 'files', 'stats'
        ));
    }
}
