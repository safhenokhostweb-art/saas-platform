<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketActivity;
use SaaSPlatform\Modules\Tickets\Models\TicketVote;
use App\Models\Setting;

/**
 * SUPPORT-SUITE-01 — Sections K, L, M, N
 * Feature Request workflow: status transitions, voting, demand dashboard.
 */
class FeatureRequestController extends Controller
{
    /**
     * Section N: Feature Request demand dashboard.
     * Top requested / recently approved / released.
     */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'top');

        $query = Ticket::withoutTenantIsolation()
            ->where('type', Ticket::TYPE_FEATURE_REQUEST)
            ->withCount('votes');

        $topRequested = (clone $query)
            ->whereNotIn('fr_status', [Ticket::FR_REJECTED, Ticket::FR_RELEASED])
            ->orderByDesc('votes_count')
            ->limit(20)
            ->get();

        $recentApproved = (clone $query)
            ->where('fr_status', Ticket::FR_APPROVED)
            ->orderByDesc('fr_status_updated_at')
            ->limit(10)
            ->get();

        $released = (clone $query)
            ->where('fr_status', Ticket::FR_RELEASED)
            ->orderByDesc('fr_status_updated_at')
            ->limit(10)
            ->get();

        return view('tickets::admin.feature-requests.index', compact(
            'topRequested', 'recentApproved', 'released', 'tab'
        ));
    }

    /**
     * Section K: Advance a Feature Request through its 10-state workflow.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'fr_status'              => 'required|string|in:' . implode(',', array_keys(Ticket::frStatuses())),
            'fr_estimated_quarter'   => 'nullable|string|max:20',
            'fr_impact_score'        => 'nullable|integer|min:0|max:100',
        ]);

        $ticket = Ticket::withoutTenantIsolation()->findOrFail($id);

        if (!$ticket->isFeatureRequest()) {
            return response()->json(['error' => 'این تیکت از نوع درخواست ویژگی نیست.'], 422);
        }

        $oldStatus = $ticket->fr_status;
        $newStatus = $request->fr_status;

        $ticket->update([
            'fr_status'              => $newStatus,
            'fr_status_updated_at'   => now(),
            'fr_estimated_quarter'   => $request->fr_estimated_quarter ?? $ticket->fr_estimated_quarter,
            'fr_impact_score'        => $request->fr_impact_score        ?? $ticket->fr_impact_score,
        ]);

        // Log the status change in ticket_activities
        TicketActivity::create([
            'ticket_id'   => $ticket->id,
            'actor_id'    => auth()->id(),
            'action_type' => 'status_changed',
            'old_value'   => $oldStatus,
            'new_value'   => $newStatus,
            'metadata'    => json_encode(['context' => 'feature_request']),
        ]);

        return response()->json([
            'success'       => true,
            'fr_status'     => $newStatus,
            'fr_status_label' => $ticket->frStatusLabel(),
            'fr_status_index' => $ticket->frStatusIndex(),
            'message'       => 'وضعیت درخواست به «' . $ticket->frStatusLabel() . '» تغییر کرد.',
        ]);
    }

    /**
     * Section N: Customer/admin votes for a feature request.
     */
    public function vote(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::withoutTenantIsolation()->findOrFail($id);

        if (!$ticket->isFeatureRequest()) {
            return response()->json(['error' => 'تنها درخواست‌های ویژگی قابل رأی‌دهی هستند.'], 422);
        }

        $userId = auth()->id();
        $mobile = $request->user()?->mobile;

        // Check if already voted
        $existing = TicketVote::where('ticket_id', $ticket->id)
            ->where(function ($q) use ($userId, $mobile) {
                $q->where('voter_id', $userId);
                if ($mobile) {
                    $q->orWhere('voter_mobile', $mobile);
                }
            })
            ->first();

        if ($existing) {
            // Toggle off (unvote)
            $existing->delete();
            $voteCount = $ticket->votes()->count();
            return response()->json(['voted' => false, 'vote_count' => $voteCount]);
        }

        TicketVote::create([
            'ticket_id'    => $ticket->id,
            'voter_id'     => $userId,
            'voter_mobile' => $mobile,
            'voter_name'   => auth()->user()?->name,
        ]);

        $voteCount = $ticket->votes()->count();
        return response()->json(['voted' => true, 'vote_count' => $voteCount]);
    }
}
